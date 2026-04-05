<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test {email? : Email пользователя}
                                       {--token= : FCM-токен напрямую}';

    protected $description = 'Отправить тестовое push-уведомление на телефон';

    public function handle(Messaging $messaging): int
    {
        $fcmToken = $this->option('token');

        if (!$fcmToken) {
            $email = $this->argument('email');

            if (!$email) {
                $users = User::whereNotNull('fcm_token')->get(['id', 'email', 'username']);

                if ($users->isEmpty()) {
                    $this->error('Нет пользователей с FCM-токеном. Сначала войдите в приложение на iPhone.');
                    return self::FAILURE;
                }

                $labels = $users->map(fn($u) => "{$u->email} ({$u->username})")->toArray();
                $choice = $this->choice('Выберите пользователя:', $labels);
                $index = array_search($choice, $labels);
                $fcmToken = $users[$index]->fcm_token;
                $this->info("Отправляем на: {$users[$index]->email}");
            } else {
                $user = User::where('email', $email)->first();

                if (!$user) {
                    $this->error("Пользователь с email {$email} не найден.");
                    return self::FAILURE;
                }

                if (!$user->fcm_token) {
                    $this->error("У пользователя нет FCM-токена. Сначала откройте приложение на iPhone.");
                    return self::FAILURE;
                }

                $fcmToken = $user->fcm_token;
                $this->info("Отправляем на: {$user->email}");
            }
        }

        $message = CloudMessage::new()->withToken($fcmToken)
            ->withNotification(Notification::create(
                'Тестовое уведомление',
                'Push-уведомления работают! Кофейня ждёт вас.'
            ))
            ->withData(['type' => 'test']);

        try {
            $messaging->send($message);
            $this->info('Уведомление успешно отправлено!');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
