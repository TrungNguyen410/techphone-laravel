#!/bin/sh
# Kiểm tra nhanh mã HTTP của các trang chính (chạy bên trong container app):
#   docker exec techphone-laravel-app sh docker/smoke-test.sh
cd /var/www/html || exit 1
BASE=${BASE:-http://localhost}
JAR=/tmp/smoke-cookies.txt
rm -f "$JAR"
fail=0

check() {
  expected=$1
  path=$2
  code=$(curl -s -b "$JAR" -c "$JAR" -o /tmp/smoke.html -w '%{http_code}' "$BASE$path")
  if [ "$code" = "$expected" ]; then
    echo "OK   $code $path"
  else
    echo "FAIL $code (mong đợi $expected) $path"
    grep -m1 -o '<title>[^<]*' /tmp/smoke.html
    fail=1
  fi
}

# Đăng nhập bằng form thật (lấy CSRF token từ trang login).
login() {
  rm -f "$JAR"
  token=$(curl -s -c "$JAR" -b "$JAR" "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$JAR" -c "$JAR" -o /dev/null -X POST "$BASE/login" \
    --data-urlencode "_token=$token" --data-urlencode "identifier=$1" --data-urlencode "password=${SEED_PASSWORD:-123456}"
}

echo "== Khách vãng lai"
for p in / /products '/products?brand=Apple&sort=price-asc&battery=5000' '/products?q=galaxy&page=2' /products/1 \
  /accessories '/accessories?category=Tai%20nghe' /accessories/1 /cart /compare /favorites /reviews '/reviews?rating=5' \
  /contact /order-lookup '/order-lookup?order_number=TP260601&phone=0911111111' '/order-lookup?order_number=XX&phone=0911111111' \
  /policies/warranty /policies/payment /sitemap.xml /login /register /forgot-password '/search/suggest?q=iphone'; do
  check 200 "$p"
done
check 404 /khong-ton-tai
check 404 /products/999999
check 302 /checkout
check 302 /admin/dashboard

echo "== Khách hàng 0911111111"
login 0911111111
for p in /account '/account?tab=orders' '/account?tab=password' /cart /checkout; do check 200 "$p"; done
check 403 /admin/dashboard

echo "== Quản trị 0900000000"
login 0900000000
for p in /admin/dashboard '/admin/dashboard?year=2025' /admin/products '/admin/products?create=1' '/admin/products?edit=1' \
  /admin/accessories '/admin/accessories?edit=1' /admin/orders '/admin/orders?status=pending' '/admin/orders?view=1' \
  /admin/customers /admin/contacts '/admin/contacts?view=1' /admin/reviews /admin/banners '/admin/banners?edit=1' \
  /admin/vouchers '/admin/vouchers?create=1' /admin/categories /admin/brands '/admin/brands?edit=1' /admin/settings; do
  check 200 "$p"
done

[ $fail = 0 ] && echo "TẤT CẢ ĐỀU ĐẠT" || echo "CÓ TRANG LỖI"
exit $fail
