<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Support\VietnamesePhone;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioSmsSender
{
    public function isConfigured(): bool
    {
        return filled(config('services.twilio.account_sid'))
            && filled(config('services.twilio.auth_token'))
            && filled(config('services.twilio.from'));
    }

    /** Gửi SMS, trả về mã theo dõi (SID) của Twilio. */
    public function send(string $phone, string $body): ?string
    {
        $sid = config('services.twilio.account_sid');

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, config('services.twilio.auth_token'))
                ->timeout(8)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => VietnamesePhone::toE164($phone),
                    'From' => config('services.twilio.from'),
                    'Body' => $body,
                ])
                ->throw();

            return $response->json('sid');
        } catch (Throwable) {
            throw new BusinessException('Không thể gửi SMS xác minh. Vui lòng thử lại sau.', 503);
        }
    }
}
