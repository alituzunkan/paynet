<?php

Route::group(['middleware' => ['web']], function () {
    Route::prefix('paynet/standard')->group(function () {

        Route::get('/redirect', 'Innovia\Paynet\Http\Controllers\StandardController@redirect')->name('paynet.standard.redirect');

        Route::get('/success', 'Innovia\Paynet\Http\Controllers\StandardController@success')->name('paynet.standard.success');

        Route::get('/cancel', 'Innovia\Paynet\Http\Controllers\StandardController@cancel')->name('paynet.standard.cancel');
    });
});

Route::post('paynet/standard/charge', 'Innovia\Paynet\Http\Controllers\StandardController@charge')->name('paynet.standard.charge');
