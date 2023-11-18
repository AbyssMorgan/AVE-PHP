<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitaecfb81923c65b9c1a4c2698e041983a
{
    public static $files = array (
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' =>
        array (
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Polyfill\\Ctype\\' => 23,
            'Symfony\\Component\\DomCrawler\\' => 29,
            'Symfony\\Component\\CssSelector\\' => 30,
        ),
        'M' =>
        array (
            'Masterminds\\' => 12,
        ),
        'L' =>
        array (
            'Liborm85\\ComposerVendorCleaner\\' => 31,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Mbstring\\' =>
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Polyfill\\Ctype\\' =>
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
        'Symfony\\Component\\DomCrawler\\' =>
        array (
            0 => __DIR__ . '/..' . '/symfony/dom-crawler',
        ),
        'Symfony\\Component\\CssSelector\\' =>
        array (
            0 => __DIR__ . '/..' . '/symfony/css-selector',
        ),
        'Masterminds\\' =>
        array (
            0 => __DIR__ . '/..' . '/masterminds/html5/src',
        ),
        'Liborm85\\ComposerVendorCleaner\\' =>
        array (
            0 => __DIR__ . '/..' . '/liborm85/composer-vendor-cleaner/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'F' =>
        array (
            'FtpClient' =>
            array (
                0 => __DIR__ . '/..' . '/nicolab/php-ftp-client/src',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitaecfb81923c65b9c1a4c2698e041983a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitaecfb81923c65b9c1a4c2698e041983a::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitaecfb81923c65b9c1a4c2698e041983a::$prefixesPsr0;
            $loader->classMap = ComposerStaticInitaecfb81923c65b9c1a4c2698e041983a::$classMap;

        }, null, ClassLoader::class);
    }
}
