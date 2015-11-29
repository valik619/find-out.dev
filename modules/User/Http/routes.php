<?php

Route::group(['namespace' => 'Modules\User\Http\Controllers'], function() {
	Route::get('/registration/{token}', 'UserController@activation');
	Route::get('/user{id}', 'UserController@profile');
	// Settings
	Route::get('/settings', 'SettingController@index');
	Route::get('/settings/data', 'SettingController@data');
	Route::get('/settings/contacts', 'SettingController@contacts');
	Route::get('/settings/privacy', 'SettingController@privacy');
});

Route::group(['prefix' => 'user', 'namespace' => 'Modules\User\Http\Controllers'], function()
{
	Route::get('/', 'UserController@index');
	Route::get('/profile/edit', 'UserController@update');
	Route::post('/profile/update', 'UserController@postUpdate');
	Route::post('/save', 'UserController@postSave');
});

Route::group(['namespace' => 'Modules\User\Http\Controllers'], function()
{
	// Authentication routes
	Route::get('login', 'UserController@getLogin');
	Route::post('login', 'UserController@postLogin');
	Route::get('logout', 'UserController@getLogout');

	// Registration routes
	Route::post('registration', 'UserController@postRegistration');
});