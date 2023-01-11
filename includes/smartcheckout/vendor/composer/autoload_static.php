<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb97636f0223ac27f0d4fad9df65f97c8
{
    public static $prefixLengthsPsr4 = array (
        'n' => 
        array (
            'nicoSWD\\Rule\\' => 13,
        ),
        'S' => 
        array (
            'SmartCheckoutSDK\\' => 17,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'nicoSWD\\Rule\\' => 
        array (
            0 => __DIR__ . '/..' . '/nicoswd/php-rule-parser/src',
        ),
        'SmartCheckoutSDK\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb97636f0223ac27f0d4fad9df65f97c8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb97636f0223ac27f0d4fad9df65f97c8::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
