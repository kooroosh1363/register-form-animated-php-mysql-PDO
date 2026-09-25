<?php

declare(strict_types=1);

final class RegistrationService
{
    public function __construct(private AccountRepository $accounts) {}

    /** @param array{username:string,email:string,password:string,password_confirm:string} $data
     *  @return array{ok:bool,error:?string,user:?array{id:int,username:string,email:string,status:string}}
     */
    public function register(array $data, int $now): array
    {
        if ($this->accounts->emailExists($data['email'])) {
            return [
                'ok' => false,
                'error' => 'An account with that email already exists.',
                'user' => null,
            ];
        }

        try {
            $id = $this->accounts->createPendingUser(
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $now,
            );
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000'
                || str_contains($error->getMessage(), 'UNIQUE constraint failed')
                || str_contains($error->getMessage(), 'Duplicate entry')) {
                return [
                    'ok' => false,
                    'error' => 'An account with that email already exists.',
                    'user' => null,
                ];
            }

            throw $error;
        }

        $user = $this->accounts->findById($id);
        if ($user === null) {
            throw new RuntimeException('Created account could not be reloaded.');
        }

        return [
            'ok' => true,
            'error' => null,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'status' => $user['status'],
            ],
        ];
    }
}
