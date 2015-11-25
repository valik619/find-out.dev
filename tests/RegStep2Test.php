<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Eloquent\Model;

class RegStep2Test extends TestCase
{
    /**
     * Проверка активации и входа
     * (второй шаг регистрации, где нужно заполнить все данные)
     */
    public function testActivation()
    {
        $email = str_random(6).'@test.ru';
        $login = str_random(8);
        $password = str_random(8);
        $name = str_random(8);
        $surname = str_random(6);
        $anonymNick = str_random(12);

        $this->visit('/')
            ->type($email, 'email')
            ->press('Регистрация')
            ->see('Вы успешно зарегистрировались!');

        $this->seeInDatabase('users_activation', [
            'email' => $email
        ]);

        $token = DB::table('users_activation')
            ->where('email', '=', $email)
            ->value('token');

        $this->visit('/user/activation/'.$token)
            ->see('Для продолжения регистрации заполните пожалуйста все поля')
            ->type($login, 'login')
            ->type($password, 'password')
            ->type($name, 'name')
            ->type($surname, 'surname')
            ->type($anonymNick, 'anonymNick')
            ->press('Регистрация')
            ->see('Выход');

        $this->seeInDatabase('users', [
            'email' => $email,
            'login' => $login,
            'name' => $name,
            'surname' => $surname,
        ]);

    }
}