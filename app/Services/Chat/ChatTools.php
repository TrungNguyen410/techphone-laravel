<?php

namespace App\Services\Chat;

use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\KnowledgeArticle;
use App\Models\Order;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\OrderService;
use App\Services\SettingService;
use App\Services\VoucherService;
use App\Support\Chat\BudgetParser;
use App\Support\Chat\CustomerNeeds;
use App\Support\Vietnamese;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * 7 công cụ (function calling) chatbot dùng để lấy dữ liệu THẬT của cửa hàng, không để mô hình tự bịa giá,
 * tồn kho hay chính sách. Chế độ offline (không có Gemini) cũng gọi đúng các công cụ này.
 */
class ChatTools
{
    private const MAX_RESULTS = 5;

    // Cùng hạn mức với trang tra cứu đơn để không thể dùng chatbot dò mã đơn nhanh hơn.
    private const ORDER_LOOKUP_MAX = 10;

    private const ORDER_LOOKUP_WINDOW = 15 * 60;

    private const SORTS = [
        'price_asc' => ['price', 'asc'],
        'price_desc' => ['price', 'desc'],
        'best_selling' => ['sold', 'desc'],
        'rating' => ['rating', 'desc'],
    ];

    public const LABELS = [
        'search_products' => 'Tìm điện thoại',
        'get_product_details' => 'Chi tiết sản phẩm',
        'compare_products' => 'So sánh máy',
        'search_accessories' => 'Tìm phụ kiện',
        'lookup_order' => 'Tra cứu đơn',
        'list_promotions' => 'Khuyến mãi',
        'search_store_policies' => 'Chính sách cửa hàng',
    ];

    /** @var array<int, string>|null */
    private ?array $brandNames = null;

    public function __construct(
        private readonly OrderService $orders,
        private readonly VoucherService $vouchers,
        private readonly SettingService $settings,
    ) {}

    /** Khai báo công cụ cho Gemini (tập con OpenAPI schema). */
    public function declarations(): array
    {
        return [
            [
                'name' => 'search_products',
                'description' => 'Tìm điện thoại đang bán tại cửa hàng theo từ khóa, hãng, khoảng giá. Dùng khi khách hỏi tư vấn chọn máy.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Từ khóa: tên máy, chip, nhu cầu như "chơi game", "camera", "pin trâu"'],
                        'brand' => ['type' => 'string', 'description' => 'Tên hãng, ví dụ Apple, Samsung, Xiaomi'],
                        'minPrice' => ['type' => 'number', 'description' => 'Giá thấp nhất, số tiền đầy đủ bằng đồng, ví dụ 20000000 cho 20 triệu'],
                        'maxPrice' => ['type' => 'number', 'description' => 'Giá cao nhất, số tiền đầy đủ bằng đồng, ví dụ 25000000 cho 25 triệu'],
                        'need' => [
                            'type' => 'string',
                            'enum' => CustomerNeeds::keys(),
                            'description' => 'Nhu cầu chính: battery (pin trâu), camera (chụp ảnh), gaming (chơi game), big_screen (màn hình lớn), compact (nhỏ gọn)',
                        ],
                        'sortBy' => ['type' => 'string', 'enum' => array_keys(self::SORTS)],
                    ],
                ],
            ],
            [
                'name' => 'get_product_details',
                'description' => 'Lấy thông số chi tiết, giá, tồn kho của một điện thoại theo tên hoặc mã sản phẩm.',
                'parameters' => ['type' => 'object', 'properties' => ['productId' => ['type' => 'string'], 'name' => ['type' => 'string']]],
            ],
            [
                'name' => 'compare_products',
                'description' => 'So sánh thông số 2-3 điện thoại theo tên. Dùng khi khách muốn so sánh máy.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['names' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tên các máy cần so sánh']],
                    'required' => ['names'],
                ],
            ],
            [
                'name' => 'search_accessories',
                'description' => 'Tìm phụ kiện (ốp lưng, sạc, tai nghe, cáp, sạc dự phòng) đang bán.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string'],
                        'minPrice' => ['type' => 'number', 'description' => 'Giá thấp nhất, số tiền đầy đủ bằng đồng'],
                        'maxPrice' => ['type' => 'number', 'description' => 'Giá cao nhất, số tiền đầy đủ bằng đồng'],
                    ],
                ],
            ],
            [
                'name' => 'lookup_order',
                'description' => 'Tra cứu trạng thái đơn hàng. Bắt buộc có mã đơn (bắt đầu bằng TP) và số điện thoại người nhận.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['orderNumber' => ['type' => 'string'], 'phone' => ['type' => 'string']],
                    'required' => ['orderNumber', 'phone'],
                ],
            ],
            [
                'name' => 'list_promotions',
                'description' => 'Liệt kê các mã giảm giá/khuyến mãi đang còn hiệu lực, hoặc kiểm tra một mã cụ thể với giá trị đơn hàng.',
                'parameters' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string'], 'subtotal' => ['type' => 'number']]],
            ],
            [
                'name' => 'search_store_policies',
                'description' => 'Tra cứu chính sách cửa hàng: bảo hành, đổi trả, giao hàng, thanh toán, trả góp, địa chỉ, hotline, giờ mở cửa.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']],
            ],
        ];
    }

    /**
     * Chạy một công cụ. Lỗi trong công cụ được trả về cho mô hình dưới dạng dữ liệu, không làm hỏng cuộc trò chuyện.
     *
     * @return array{ok: bool, result: array<string, mixed>, cards: array<int, array<string, mixed>>}
     */
    public function execute(string $name, array $args = [], array $context = []): array
    {
        $method = lcfirst(str_replace('_', '', ucwords($name, '_')));
        if (! array_key_exists($name, self::LABELS) || ! method_exists($this, $method)) {
            return ['ok' => false, 'result' => ['error' => "Công cụ {$name} không tồn tại"], 'cards' => []];
        }

        try {
            $output = $this->{$method}($args, $context);
            $cards = $output['cards'] ?? [];
            unset($output['cards']);

            return ['ok' => true, 'result' => $output, 'cards' => $cards];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'result' => ['error' => 'Không lấy được dữ liệu, vui lòng thử lại'], 'cards' => []];
        }
    }

    private function searchProducts(array $args): array
    {
        $budget = BudgetParser::normalizeArgs($args);
        $need = in_array($args['need'] ?? null, CustomerNeeds::keys(), true) ? $args['need'] : null;
        $brand = trim((string) ($args['brand'] ?? ''));
        $criteria = $budget + array_filter(['brand' => $brand, 'need' => $need]);
        $brandIds = $this->brandIds($brand);
        if ($brandIds === []) {
            return ['products' => [], 'criteria' => $criteria, 'note' => "Cửa hàng chưa kinh doanh hãng {$brand}", 'cards' => []];
        }

        // Kiểm tra lần cuối trước khi trả lời: chỉ giữ máy đúng khoảng giá và hãng.
        $matches = fn (Product $product) => $this->inBudget($product->price, $budget) && ($brandIds === null || in_array((int) $product->brand_id, $brandIds, true));
        $base = fn () => Product::active()
            ->when($budget['minPrice'] ?? null, fn (Builder $query, $min) => $query->where('price', '>=', $min))
            ->when($budget['maxPrice'] ?? null, fn (Builder $query, $max) => $query->where('price', '<=', $max))
            ->when($brandIds !== null, fn (Builder $query) => $query->whereIn('brand_id', $brandIds));
        [$sortColumn, $sortDirection] = self::SORTS[$args['sortBy'] ?? ''] ?? ['sold', 'desc'];
        // Có nhu cầu thì xếp hạng mọi máy phù hợp theo thông số, không thì ưu tiên máy bán chạy.
        $limit = $need ? null : self::MAX_RESULTS;
        $fetch = fn (Builder $query) => $query->orderBy($sortColumn, $sortDirection)->orderBy('id')->when($limit, fn ($q) => $q->limit($limit))->get();

        $keyword = trim((string) ($args['keyword'] ?? ''));
        $products = $fetch($this->keywordFilter($base(), $keyword, ['name', 'chip', 'description', 'camera', 'battery', 'ram', 'storage']))->filter($matches)->values();
        $note = '';
        $outOfBudget = false;
        if ($products->isEmpty() && $keyword !== '') {
            $products = $fetch($base())->filter($matches)->values();
            if ($products->isNotEmpty()) {
                $note = 'Không có máy khớp từ khóa, đây là các máy phù hợp tiêu chí giá/hãng.';
            }
        }
        // Không có máy trong tầm giá: gợi ý các máy có giá gần khoảng khách hỏi nhất (không phải cứ rẻ nhất).
        if ($products->isEmpty() && $budget) {
            $candidates = Product::active()
                ->when($brandIds !== null, fn (Builder $query) => $query->whereIn('brand_id', $brandIds))
                ->orderByDesc('sold')->orderBy('id')->get();
            $products = $candidates->values()
                ->map(fn (Product $product, int $index) => [$product, $index, $this->budgetDistance($product->price, $budget)])
                ->sort(fn ($a, $b) => $a[2] <=> $b[2] ?: $a[1] <=> $b[1])
                ->take(3)
                ->map(fn ($row) => $row[0])
                ->values();
            if ($products->isNotEmpty()) {
                $outOfBudget = true;
                $note = 'Không có máy trong tầm giá khách yêu cầu. Đây là các máy có giá gần nhất — hãy nói rõ với khách là ngoài ngân sách.';
            }
        }
        if ($need && ! $outOfBudget) {
            $products = collect(CustomerNeeds::rank($products, $need))->take(self::MAX_RESULTS)->values();
        }

        return array_filter([
            'products' => $products->map(fn (Product $product) => $this->productSummary($product))->all(),
            'criteria' => $criteria,
            'note' => $note ?: null,
            'outOfBudget' => $outOfBudget ?: null,
            'cards' => $products->map(fn (Product $product) => $this->card($product))->all(),
        ], fn ($value) => $value !== null);
    }

    private function getProductDetails(array $args): array
    {
        $product = ! empty($args['productId'])
            ? Product::find((int) $args['productId'])
            : $this->findProductsByName((string) ($args['name'] ?? ''))->first();
        if (! $product || $product->status !== 'active') {
            return ['found' => false];
        }

        return [
            'found' => true,
            'product' => $this->productSummary($product) + [
                'description' => mb_substr((string) $product->description, 0, 600),
                'specifications' => $product->specifications ?: (object) [],
            ],
            'cards' => [$this->card($product)],
        ];
    }

    private function compareProducts(array $args): array
    {
        $names = array_slice(array_values(array_filter((array) ($args['names'] ?? []), 'is_string')), 0, 3);
        $found = collect();
        $missing = [];
        foreach ($names as $name) {
            $product = $this->findProductsByName($name)->first();
            if ($product && ! $found->contains('id', $product->id)) {
                $found->push($product);
            } elseif (! $product) {
                $missing[] = $name;
            }
        }

        return [
            'products' => $found->map(fn (Product $product) => $this->productSummary($product))->all(),
            'missing' => $missing,
            'cards' => $found->map(fn (Product $product) => $this->card($product))->all(),
        ];
    }

    private function searchAccessories(array $args): array
    {
        $budget = BudgetParser::normalizeArgs($args);
        $keyword = trim((string) ($args['keyword'] ?? ''));
        $categoryIds = $this->matchingCategoryIds($keyword);
        $words = $this->keywordWords($keyword);

        $accessories = Accessory::active()
            ->when($budget['minPrice'] ?? null, fn (Builder $query, $min) => $query->where('price', '>=', $min))
            ->when($budget['maxPrice'] ?? null, fn (Builder $query, $max) => $query->where('price', '<=', $max))
            ->when($words || $categoryIds, fn (Builder $query) => $query->where(function (Builder $inner) use ($words, $categoryIds) {
                if ($words) {
                    $inner->orWhere(fn (Builder $all) => $this->keywordFilter($all, implode(' ', $words), ['name', 'description']));
                }
                if ($categoryIds) {
                    $inner->orWhereIn('category_id', $categoryIds);
                }
            }))
            ->orderByDesc('sold')->orderBy('id')->limit(self::MAX_RESULTS)->get()
            ->filter(fn (Accessory $item) => $this->inBudget($item->price, $budget))
            ->values();

        return [
            'criteria' => $budget ?: (object) [],
            'accessories' => $accessories->map(fn (Accessory $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'inStock' => $item->stock > 0,
                'url' => route('accessories.show', $item, false),
            ])->all(),
            'cards' => $accessories->map(fn (Accessory $item) => $this->card($item))->all(),
        ];
    }

    private function lookupOrder(array $args, array $context): array
    {
        $key = 'order-lookup-chat:'.($context['clientKey'] ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, self::ORDER_LOOKUP_MAX)) {
            return ['found' => false, 'limited' => true, 'message' => 'Bạn đã tra cứu đơn hàng quá nhiều lần. Vui lòng thử lại sau 15 phút.'];
        }
        RateLimiter::hit($key, self::ORDER_LOOKUP_WINDOW);

        try {
            $order = $this->orders->lookup(trim((string) ($args['orderNumber'] ?? '')), (string) ($args['phone'] ?? ''));
        } catch (BusinessException $exception) {
            return ['found' => false, 'message' => $exception->getMessage()];
        }

        return [
            'found' => true,
            'order' => [
                'orderNumber' => $order->order_number,
                'status' => Order::statusLabelOf($order->status),
                'createdAt' => $order->created_at?->toIso8601String(),
                'total' => $order->total,
                'paymentStatus' => $order->paymentStatusLabel(),
                'items' => $order->items->map(fn ($item) => "{$item->name} x{$item->quantity}")->all(),
                'shippingProvider' => $order->shipping_provider,
                'trackingNumber' => $order->tracking_number,
                'estimatedDelivery' => $order->estimated_delivery?->toDateString(),
            ],
        ];
    }

    private function listPromotions(array $args): array
    {
        if (! empty($args['code'])) {
            try {
                $subtotal = is_numeric($args['subtotal'] ?? null) && (int) $args['subtotal'] > 0 ? (int) $args['subtotal'] : PHP_INT_MAX;
                // Không truyền người dùng: voucher tặng riêng không bao giờ lộ qua chatbot.
                $voucher = $this->vouchers->validate((string) $args['code'], $subtotal);

                return ['valid' => true, 'voucher' => $this->publicVoucher($voucher)];
            } catch (BusinessException $exception) {
                return ['valid' => false, 'message' => $exception->getMessage()];
            }
        }

        $vouchers = Voucher::public()
            ->where('active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->where(fn (Builder $query) => $query->where('quantity', 0)->orWhereColumn('used', '<', 'quantity'))
            ->latest('id')->limit(10)->get();

        return ['vouchers' => $vouchers->map(fn (Voucher $voucher) => $this->publicVoucher($voucher))->all()];
    }

    private function searchStorePolicies(array $args): array
    {
        $store = $this->settings->all();

        return [
            'articles' => $this->searchKnowledge((string) ($args['query'] ?? ''))
                ->map(fn (KnowledgeArticle $article) => ['title' => $article->title, 'content' => $article->content])->all(),
            'store' => [
                'storeName' => $store['storeName'] ?? '',
                'hotline' => $store['hotline'] ?? '',
                'email' => $store['email'] ?? '',
                'address' => $store['address'] ?? '',
            ],
        ];
    }

    /**
     * Tìm bài viết phù hợp nhất: cụm từ khóa nguyên vẹn ("doi tra") nặng điểm hơn từ đơn lẻ ("tra").
     *
     * @return SupportCollection<int, KnowledgeArticle>
     */
    public function searchKnowledge(string $query, int $limit = 3): SupportCollection
    {
        $articles = KnowledgeArticle::where('active', true)->latest('updated_at')->get();
        $tokens = Vietnamese::tokens($query);
        $terms = array_unique($tokens);
        $plainQuery = ' '.implode(' ', $tokens).' ';
        if (! $terms) {
            return $articles->take($limit)->values();
        }

        return $articles
            ->map(function (KnowledgeArticle $article) use ($terms, $plainQuery) {
                $keywords = $article->keywords ?? [];
                $keywordTokens = Vietnamese::tokens(implode(' ', $keywords));
                $titleTokens = Vietnamese::tokens($article->title);
                $bodyTokens = Vietnamese::tokens($article->content);
                $score = 0;
                foreach ($keywords as $keyword) {
                    $phrase = implode(' ', Vietnamese::tokens($keyword));
                    if (str_contains($phrase, ' ') && str_contains($plainQuery, ' '.$phrase.' ')) {
                        $score += 6;
                    }
                }
                foreach ($terms as $term) {
                    $score += in_array($term, $keywordTokens, true) ? 3 : 0;
                    $score += in_array($term, $titleTokens, true) ? 2 : 0;
                    $score += in_array($term, $bodyTokens, true) ? 1 : 0;
                }

                return [$article, $score];
            })
            ->filter(fn ($row) => $row[1] > 0)
            ->sortByDesc(fn ($row) => $row[1])
            ->take($limit)
            ->map(fn ($row) => $row[0])
            ->values();
    }

    /** Dữ liệu thẻ sản phẩm hiển thị trong khung chat (kèm nút thêm vào giỏ). */
    public function card(Model $item): array
    {
        $type = $item instanceof Accessory ? 'accessory' : 'product';

        return [
            'id' => $item->id,
            'type' => $type,
            'name' => $item->name,
            'price' => $item->price,
            'oldPrice' => (int) $item->old_price,
            'image' => media_url($item->image),
            'url' => $type === 'accessory' ? route('accessories.show', $item, false) : route('products.show', $item, false),
            'stock' => (int) $item->stock,
        ];
    }

    private function productSummary(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $this->brandName((int) $product->brand_id),
            'price' => $product->price,
            'oldPrice' => (int) $product->old_price,
            'ram' => $product->ram,
            'storage' => $product->storage,
            'chip' => $product->chip,
            'screen' => $product->screen,
            'battery' => $product->battery,
            'camera' => $product->camera,
            'rating' => (float) $product->rating,
            'inStock' => $product->stock > 0,
            'stock' => $product->stock,
            'url' => route('products.show', $product, false),
        ];
    }

    private function publicVoucher(Voucher $voucher): array
    {
        return [
            'code' => $voucher->code,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'minOrder' => $voucher->min_order,
            'maxDiscount' => $voucher->max_discount,
            'endDate' => $voucher->end_date?->toDateString(),
        ];
    }

    /**
     * Khớp mọi từ của tên máy; không có kết quả thì thử lại sau khi bỏ tên hãng mô hình hay thêm vào
     * ("Samsung Galaxy S25 Ultra" → "Galaxy S25 Ultra").
     *
     * @return Collection<int, Product>
     */
    private function findProductsByName(string $name, int $limit = 1): Collection
    {
        $search = fn (string $query) => $this->keywordFilter(Product::active(), $query, ['name'])
            ->orderByDesc('sold')->orderBy('id')->limit($limit)->get();
        $exact = $search($name);
        if ($exact->isNotEmpty() || trim($name) === '') {
            return $exact;
        }

        $brandWords = array_map(fn ($brand) => Vietnamese::fold($brand), $this->brandNames());
        $withoutBrand = implode(' ', array_filter(preg_split('/\s+/', trim($name)) ?: [], fn ($word) => ! in_array(Vietnamese::fold($word), $brandWords, true)));

        return $withoutBrand !== '' && $withoutBrand !== trim($name) ? $search($withoutBrand) : new Collection;
    }

    /** @return array<int, string> */
    private function keywordWords(string $keyword): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($keyword)) ?: [], fn ($word) => mb_strlen($word) > 1));
    }

    /**
     * Mọi từ đều phải xuất hiện ở ít nhất một cột. Collation utf8mb4_unicode_ci của MySQL so khớp
     * không phân biệt hoa thường và dấu, nên "pin trau" vẫn tìm được "pin trâu".
     *
     * @param  array<int, string>  $columns
     */
    private function keywordFilter(Builder $query, string $keyword, array $columns): Builder
    {
        foreach ($this->keywordWords($keyword) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(function (Builder $inner) use ($columns, $like) {
                foreach ($columns as $column) {
                    $inner->orWhere($column, 'like', $like);
                }
            });
        }

        return $query;
    }

    /** @return array<int, int>|null null = không lọc theo hãng; [] = cửa hàng không có hãng này */
    private function brandIds(string $brand): ?array
    {
        if ($brand === '') {
            return null;
        }

        return Brand::where('name', 'like', '%'.addcslashes($brand, '%_\\').'%')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<int, int> Danh mục có tên chứa (hoặc nằm trong) từ khóa, ví dụ "tai nghe", "sạc dự phòng" */
    private function matchingCategoryIds(string $keyword): array
    {
        $target = trim(Vietnamese::fold($keyword));
        if ($target === '') {
            return [];
        }

        return Category::all()
            ->filter(function (Category $category) use ($target) {
                $name = trim(Vietnamese::fold($category->name));

                return $name !== '' && (str_contains($name, $target) || str_contains($target, $name));
            })
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** @return array<int, string> */
    private function brandNames(): array
    {
        return $this->brandNames ??= Brand::withTrashed()->pluck('name', 'id')->all();
    }

    private function brandName(?int $brandId): string
    {
        return $this->brandNames()[$brandId] ?? '';
    }

    private function inBudget(int $price, array $budget): bool
    {
        return (! isset($budget['minPrice']) || $price >= $budget['minPrice'])
            && (! isset($budget['maxPrice']) || $price <= $budget['maxPrice']);
    }

    /** Khoảng cách từ giá tới khoảng ngân sách; 0 khi nằm trong khoảng. */
    private function budgetDistance(int $price, array $budget): int
    {
        if (isset($budget['minPrice']) && $price < $budget['minPrice']) {
            return $budget['minPrice'] - $price;
        }
        if (isset($budget['maxPrice']) && $price > $budget['maxPrice']) {
            return $price - $budget['maxPrice'];
        }

        return 0;
    }
}
