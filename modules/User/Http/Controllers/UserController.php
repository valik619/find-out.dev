<?php namespace Modules\User\Http\Controllers;

use Modules\User\Entities\UserData;
use Modules\User\Entities\UserSetting;
use Pingpong\Modules\Routing\Controller;
use Modules\User\Entities\UsersActivation;
use Modules\User\Entities\User;
use Validator;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller {
	/**
	 * Контроллер который отвечает за регистрацию и авторизацию пользователей
	 */
	use AuthenticatesAndRegistersUsers, ThrottlesLogins;
	/**
	 * Указатель куда будет переправлен юзер после регистрации/авторизации
	 * @var string
	 */
	protected  $redirectTo = '/';
	protected  $redirectPath = '/';
	/**
	 * Указывает на страницу авторизации
	 * @var string
	 */
	protected  $loginPath = '/login';
	/**
	 * Поле по которому идет авторизация (Логин пользователя)
	 * @var string
	 */
	protected $username = 'login';
	/**
	 * Подключение посредника guest для блокировки доступа
	 * к некоторым страницам неавторизированным пользователям
	 */
	public function __construct()
	{
		$this->middleware('guest', ['except' => ['getLogout', 'update', 'profile', 'postUpdate']]);
	}
	/**
	 * Выводит профиль пользователя
	 * @param $id
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function profile($id){
		$account = User::find($id);
		$data = $account->data;
		if($account){
			return view('user::profile', [
				'account' => $account,
				'data' => $data,
			]);
		}else{
			throw new NotFoundHttpException(trans('user::messages.user.not_found'));
		}
	}

	/**
	 * Выводим страницу активации (второго шага регистрации)
	 * На ней пользователь заполняет все свои данные
	 * @param Request $request
	 * @return bool|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function activation(Request $request){
		$account = UsersActivation::where('token', $request->token)->first();
		if($account){
			return view('user::step2', [
				'email' => $account->email,
			]);
		}else{
			abort(404, trans('user::messages.tokenNotFound'));
			return false;
		}
	}
	/**
	 * ----------------------
	 * Регистрация пользователя
	 * Функция создает в таблице users_activation пользователя
	 * Отправляет ему письмо на емеил
	 * После этого он должен активировать свой аккаунт
	 * его запись перенесется в таблицу users
	 * там у нас только активированные пользователи
	 * ----------------------
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function postRegistration(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required|email|max:255|unique:users_activation|unique:users',
		]);
		if ($validator->fails()) {
			$this->throwValidationException(
				$request, $validator
			);
		}
		/**
		 * @var $data array
		 * @var $data['token'] -- Токен пользователя для активации.
		 */
		$data['token'] = str_random(32);
		$data['created_at'] = time();
		$url = url('/').'/registration/'.$data['token'];
		$data['email'] = $request->email;
		/**
		 * Отправка письма.
		 * Шаблон в modules/user/resources/views/mails/
		 * Прикрепляется ссылка $data['url'] по которой должен перейти пользователь для активации
		 */
		Mail::send('user::mails/welcome', ['url' => $url], function($message) use ($data)
		{
			$message->to($data['email'])->subject(trans('user::messages.ACCOUNT_CONFIRMATION'));
		});
		/**
		 * Создание пользователя.
		 * Записываем только емеил и токен
		 */
		if(UsersActivation::create([
			'email' => $data['email'],
			'token' => $data['token'],
		])) {
			return redirect(url('/'))->with('message', trans('user::messages.DISABLED_ACCOUNT_CREATED'));
		}else{
			return redirect(url('/'))->with('message', trans('user::messages.SAVE_ERROR'));
		}
	}
	/**
	 * Регистрация пользователя
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	public function postSave(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required|email|max:255|unique:users',
			'name' => 'max:255|min:1',
			'surname' => 'max:255|min:1',
			'gender' => 'in:0,1',
			'login' => 'required|max:64|min:2|unique:users',
			'anonymous_nick' => 'required|max:64|min:2|unique:users_data',
			'password' => 'required|min:6|max:100',
		]);
		if ($validator->fails()) {
			$this->throwValidationException(
				$request, $validator
			);
		}
		$data = $request->all();
		//Создаем пользователя
		$user = User::create([
			'login' => $data['login'],
			'email' => $data['email'],
			'password' => bcrypt($data['password']),
		]);
		if($user) {

			//Сразу Авторизовываем его
			Auth::login($user);

			//Удаляем неактивированный аккаунт
			$account = UsersActivation::where('email', $request->email)->first();
			$account->delete();

			//Сохраняем информацию о нём
			UserData::create([
				'user_id' => Auth::user()->id,
				'anonymous_nick' => $data['anonymous_nick'],
				'first_name' => $data['name'],
				'last_name' => $data['surname'],
				'gender' => $data['gender'],
			]);

			//Создаем запись в таблице настроек и записываем первые значения
			UserSetting::create([
				'user_id' => Auth::user()->id,
				'date_of_birth_view_type' => 0,
				'lang' => 'ru',
			]);

			return redirect($this->redirectPath());
		}else{
			return redirect($this->redirectPath())->with([
				'message' => trans('user::messages.reg.something_goes_wrong'),
			]);
		}
	}
	/**
	 * Выводим страницу авторизации
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function getLogin()
	{
		return view('user::login');
	}
	/**
	 * Авторизация пользователя
	 * @param Request $request
	 * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
	 */
	public function postLogin(Request $request)
	{
		$this->validate($request, [
			$this->loginUsername() => 'required', 'password' => 'required',
		]);
		$throttles = $this->isUsingThrottlesLoginsTrait();
		if ($throttles && $this->hasTooManyLoginAttempts($request)) {
			return $this->sendLockoutResponse($request);
		}
		$credentials = $this->getCredentials($request);
		if (Auth::attempt($credentials, $request->has('remember'))) {
			return $this->handleUserWasAuthenticated($request, $throttles);
		}
		if ($throttles) {
			$this->incrementLoginAttempts($request);
		}
		return redirect($this->loginPath())
			->withInput($request->only($this->loginUsername(), 'remember'))
			->withErrors([
				$this->loginUsername() => $this->getFailedLoginMessage(),
			]);
	}
	protected function getFailedLoginMessage()
	{
		return Lang::has('user::messages.auth.failed')
			? Lang::get('auth.failed')
			: 'auth.failed';
	}
}