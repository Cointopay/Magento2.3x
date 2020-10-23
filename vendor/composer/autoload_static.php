<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc71468f90ea678c38a811f26bcd79964
{
    public static $files = array (
        '5fee0288cc9dafe218ca3741730efa65' => __DIR__ . '/../..' . '/registration.php',
    );

    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'Cointopay\\PaymentGateway\\' => 25,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Cointopay\\PaymentGateway\\' => 
        array (
            0 => __DIR__ . '/../..' . '/',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc71468f90ea678c38a811f26bcd79964::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc71468f90ea678c38a811f26bcd79964::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
