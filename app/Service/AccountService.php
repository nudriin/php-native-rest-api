<?php

namespace Nurdin\BinaryTalk\Service;

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Nurdin\BinaryTalk\Config\Database;
use Nurdin\BinaryTalk\Domain\Account;
use Nurdin\BinaryTalk\Exception\ValidationException;
use Nurdin\BinaryTalk\Model\Account\AccountDeleteRequest;
use Nurdin\BinaryTalk\Model\Account\AccountDisplayResponse;
use Nurdin\BinaryTalk\Model\Account\AccountGetRequest;
use Nurdin\BinaryTalk\Model\Account\AccountGetResponse;
use Nurdin\BinaryTalk\Model\Account\AccountLoginRequest;
use Nurdin\BinaryTalk\Model\Account\AccountLoginResponse;
use Nurdin\BinaryTalk\Model\Account\AccountPasswordRequest;
use Nurdin\BinaryTalk\Model\Account\AccountPasswordResponse;
use Nurdin\BinaryTalk\Model\Account\AccountRegisterRequest;
use Nurdin\BinaryTalk\Model\Account\AccountRegisterResponse;
use Nurdin\BinaryTalk\Model\Account\AccountUpdateProfileRequest;
use Nurdin\BinaryTalk\Model\Account\AccountUpdateProfileResponse;
use Nurdin\BinaryTalk\Repository\AccountRepository;

class AccountService
{
    private AccountRepository $accountRepository;

    public function __construct(AccountRepository $accountRepository)
    {
        $this->accountRepository = $accountRepository;
        $dotenv = Dotenv::createImmutable(__DIR__ . "/../../"); // ! BISA BEGINI
        $dotenv->load();
    }

    public function register(AccountRegisterRequest $request): AccountRegisterResponse
    {
        $this->validateRegister($request);
        try {
            Database::beginTransaction();
            $account = $this->accountRepository->findAccount($request->username, 'username');
            if ($account != null) {
                throw new ValidationException("Username is already exist", 400);
            }

            $account = $this->accountRepository->findAccount($request->email, 'email');
            if ($account != null) {
                throw new ValidationException("Email is already exist", 400);
            }

            $account = new Account();
            $account->username = $request->username;
            $account->name = $request->name;
            $account->email = $request->email;
            $account->password = password_hash($request->password, PASSWORD_BCRYPT);

            $this->accountRepository->save($account);
            Database::commitTransaction();

            $response = new AccountRegisterResponse();
            $response->account = $account;

            return $response;
        } catch (ValidationException $e) {
            Database::rollbackTransaction();
            throw $e;
        }
    }

    public function validateRegister(AccountRegisterRequest $request)
    {
        if (
            $request->username == null || $request->email == null || $request->name == null || $request->password == null ||
            trim($request->username) == "" || trim($request->email) == "" || trim($request->name) == "" || trim($request->password) == ""
        ) {
            throw new ValidationException("Username, email, nama, dan password is required", 400);
        }
        
        if(!filter_var($request->email, FILTER_VALIDATE_EMAIL)){
            throw new ValidationException("Email must be valid email", 400);
        }
    }

    public function login(AccountLoginRequest $request): AccountLoginResponse
    {
        $this->validateLogin($request);
        try {
            $account = $this->accountRepository->findAccount($request->username, 'username');
            if ($account == null) {
                throw new ValidationException("Username or password is wrong", 400);
            }
            if (password_verify($request->password, $account->password)) {
                $response = new AccountLoginResponse();
                $expired_time = time() + (60 * 60);

                $payload = [
                    'username' => $account->username,
                    'exp' => $expired_time // token 1 jam
                ];

                $token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
                $response->token = $token;

                return $response;
            } else {
                throw new ValidationException("Username or password is wrong", 400);
            }
        } catch (ValidationException $e) {
            throw $e;
        }
    }


    public function validateLogin(AccountLoginRequest $request)
    {
        if (
            $request->username == null || $request->password == null ||
            trim($request->username) == "" || trim($request->password) == ""
        ) {
            throw new ValidationException("Username and password is required", 400);
        }
    }


    public function getUser(AccountGetRequest $request): AccountGetResponse
    {
        $this->ValidateGetUser($request);
        try {
            $account = $this->accountRepository->findAccount($request->username, 'username');
            if ($account == null) {
                throw new ValidationException("User not found", 404);
            }

            $response = new AccountGetResponse();
            $response->account = $account;

            return $response;
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function ValidateGetUser(AccountGetRequest $request)
    {
        if ($request->username == null || trim($request->username) == "") {
            throw new ValidationException("User not found", 404);
        }
    }

    public function updateUser(AccountUpdateProfileRequest $request): AccountUpdateProfileResponse
    {
        $this->validateAccountUpdateProfile($request);
        try {
            Database::beginTransaction();
            $account = $this->accountRepository->findAccount($request->username, 'username');
            if ($account == null) {
                throw new ValidationException("User not found", 404);
            }

            // cek jika user da ngirim request nama
            if ($request->name !== null && trim($request->name) != "") {
                $account->name = $request->name;
            }
            
            if ($request->profile_pic !== null && trim($request->profile_pic) != "") {
                $account->profile_pic = $request->profile_pic;
            }

            $this->accountRepository->update($account);
            Database::commitTransaction();

            $response = new AccountUpdateProfileResponse();
            $response->account = $account;
            return $response;
        } catch (ValidationException $e) {
            Database::rollbackTransaction();
            throw $e;
        }
    }

    public function validateAccountUpdateProfile(AccountUpdateProfileRequest $request)
    {
        if ($request->username == null || trim($request->username) == "") {
            throw new ValidationException("User not found", 404);
        }
    }

    public function changePassword(AccountPasswordRequest $request): AccountPasswordResponse
    {
        $this->validateChangePassword($request);
        try {
            Database::beginTransaction();
            $account = $this->accountRepository->findAccount($request->username, 'username');
            if ($account == null) {
                throw new ValidationException("User not found", 404);
            }

            if (!password_verify($request->oldPassword, $account->password)) {
                throw new ValidationException("Old password is wrong", 400);
            }

            $account->password = password_hash($request->newPassword, PASSWORD_BCRYPT);
            $this->accountRepository->update($account);
            Database::commitTransaction();

            $response = new AccountPasswordResponse();
            $response->account = $account;
            return $response;
        } catch (ValidationException $e) {
            Database::rollbackTransaction();
            throw $e;
        }
    }

    public function validateChangePassword(AccountPasswordRequest $request)
    {
        if ($request->oldPassword == null || $request->newPassword == null || trim($request->oldPassword) == "" || trim($request->newPassword) == "") {
            throw new ValidationException("Password is required", 400);
        }
    }
    
    public function deleteAccount(AccountDeleteRequest $request) 
    {
        $this->validateDeleteAccount($request);
        try{
            Database::beginTransaction();
            $account = $this->accountRepository->findAccount($request->username, 'username');
            if($account == null){
                throw new ValidationException("User not found", 404);
            }
            $this->accountRepository->deleteByUsername($account->username);
            Database::commitTransaction();
        } catch(ValidationException $e){
            Database::rollbackTransaction();
            throw $e;
        }
    }
    
    
    public function validateDeleteAccount(AccountDeleteRequest $request)
    {
        if ($request->username == null || trim($request->username) == "") {
            throw new ValidationException("Password is required", 400);
        }
    }

}
