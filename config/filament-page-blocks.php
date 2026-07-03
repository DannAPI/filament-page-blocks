<?php

declare(strict_types=1);

use DannAPI\FilamentPageBlocks\Blocks\HeroBlock;
use DannAPI\FilamentPageBlocks\Blocks\ImageTextBlock;
use DannAPI\FilamentPageBlocks\Blocks\RichTextBlock;
use DannAPI\FilamentPageBlocks\Filament\Pages\MediaLibraryPage;
use DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource;
use DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource;
use DannAPI\FilamentPageBlocks\Filament\Resources\PageResource;
use DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource;
use DannAPI\FilamentPageBlocks\Filament\Resources\UserResource;
use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use DannAPI\FilamentPageBlocks\Models\Menu;
use DannAPI\FilamentPageBlocks\Models\MenuItem;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Models\PageBlock;
use DannAPI\FilamentPageBlocks\Models\Role;
use DannAPI\FilamentPageBlocks\Rendering\BladePageBlocksRenderer;
use DannAPI\FilamentPageBlocks\Rendering\DefaultFrontendDataProvider;

return [
    'models' => [
        'page' => Page::class,
        'page_block' => PageBlock::class,
        'general_info' => GeneralInfo::class,
        'menu' => Menu::class,
        'menu_item' => MenuItem::class,
        'role' => Role::class,
    ],
    'tables' => [
        'pages' => 'pages',
        'page_blocks' => 'page_blocks',
        'general_info' => 'general_info',
        'menus' => 'menus',
        'menu_items' => 'menu_items',
        'roles' => 'roles',
        'role_user' => 'role_user',
        'users' => 'users',
    ],
    'blocks' => [HeroBlock::class, RichTextBlock::class, ImageTextBlock::class],
    'templates' => [
        'default' => [
            'label' => 'Default page',
            'blocks' => '*',
            'layout' => 'filament-page-blocks::pages.default',
        ],
    ],
    'default_template' => 'default',
    'general_info' => [
        'resource_enabled' => true,
        'resource' => GeneralInfoResource::class,
    ],
    'system_blocks' => [
        'prevent_reuse' => true,
    ],
    'slug' => [
        'auto_generate' => true,
        'separator' => '-',
    ],
    'filament' => [
        'resource_enabled' => true,
        'resource' => PageResource::class,
        'navigation_group' => 'Content',
        'navigation_sort' => 10,
        'max_content_width' => 'full',
        'page_form' => [
            'columns' => 4,
            'page_column_span' => 3,
            'seo_column_span' => 1,
        ],
        'pages' => [
            'filters_enabled' => false,
        ],
    ],
    'routes' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['web'],
        'name' => 'filament-page-blocks.show',
        'reserved_slugs' => ['admin', 'api', 'livewire'],
    ],
    'rendering' => [
        'renderer' => BladePageBlocksRenderer::class,
        'unknown_blocks' => 'skip',
    ],
    'frontend' => [
        'data_provider' => DefaultFrontendDataProvider::class,
        'header_view' => 'filament-page-blocks::parts.header',
        'footer_view' => 'filament-page-blocks::parts.footer',
        'navigation' => [
            'enabled' => true,
        ],
        'page_keys' => [
            '/' => 'home',
            'case-studies' => 'studies',
        ],
        'site' => [
            'name' => env('APP_NAME', 'Laravel'),
            'logo' => 'img/logo.png',
        ],
        'assets' => [
            'favicon' => 'favicon.ico',
            'styles' => [
                'https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Reddit+Sans:ital,wght@0,200..900;1,200..900&display=swap',
                'css/jquery.mCustomScrollbar.min.css',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
                'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lightgallery.min.css',
                'css/animate.css',
                'css/style.css',
            ],
            'scripts' => [
                'js/jquery-3.7.1.min.js',
                'js/slick.min.js',
                'js/bootstrap.min.js',
                'js/wow.min.js',
                'js/jquery.mCustomScrollbar.js',
                'https://cdn.jsdelivr.net/npm/flatpickr',
                'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/lightgallery.min.js',
                'js/main.js',
            ],
        ],
    ],
    'menus' => [
        'resource_enabled' => true,
        'resource' => MenuResource::class,
        'header' => 'header',
        'footer' => 'footer',
        'admin' => [
            'enabled' => true,
            'handle' => 'admin',
            'auto_sync' => true,
            'icons' => [
                'result_limit' => 48,
            ],
        ],
    ],
    'authorization' => [
        'permission_paths' => [app_path('Filament/Permissions')],
        'roles_resource_enabled' => true,
        'roles_resource' => RoleResource::class,
        'users_resource_enabled' => true,
        'users_resource' => UserResource::class,
        'user_model' => 'App\\Models\\User',
        'permissions' => [
            'Panel' => [
                'panel.access' => 'Access admin panel',
            ],
            'Pages' => [
                'pages.viewAny' => 'View page list',
                'pages.view' => 'View pages',
                'pages.create' => 'Create pages',
                'pages.update' => 'Update pages',
                'pages.delete' => 'Delete pages',
                'pages.restore' => 'Restore pages',
                'pages.forceDelete' => 'Force-delete pages',
                'pages.publish' => 'Publish pages',
            ],
            'General info' => [
                'general_info.viewAny' => 'View general information',
                'general_info.view' => 'View general information record',
                'general_info.create' => 'Create general information record',
                'general_info.update' => 'Update general information',
            ],
            'Menus' => [
                'menus.viewAny' => 'View menu list',
                'menus.view' => 'View menus',
                'menus.create' => 'Create menus',
                'menus.update' => 'Update menus',
                'menus.delete' => 'Delete menus',
            ],
            'Roles' => [
                'roles.viewAny' => 'View role list',
                'roles.view' => 'View roles',
                'roles.create' => 'Create roles',
                'roles.update' => 'Update roles',
                'roles.delete' => 'Delete roles',
            ],
            'Users' => [
                'users.viewAny' => 'View user list',
                'users.view' => 'View users',
                'users.create' => 'Create users',
                'users.update' => 'Update users',
                'users.delete' => 'Delete users',
            ],
            'Media' => [
                'media.viewAny' => 'Browse media library',
                'media.upload' => 'Upload media',
                'media.update' => 'Create media folders',
                'media.delete' => 'Delete media',
            ],
        ],
    ],
    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => 3600,
        'prefix' => 'filament-page-blocks',
    ],
    'admin_state' => [
        // Used only when a restored user does not already exist. Keep null to generate an unknown random password.
        'user_password' => env('PAGE_BLOCKS_STATE_USER_PASSWORD'),
    ],
    'media' => [
        'disk' => env('PAGE_BLOCKS_DISK', 'public'),
        'directory' => 'page-blocks',
        'library' => [
            'enabled' => true,
            'page' => MediaLibraryPage::class,
            'default_collection' => 'system',
            'collections' => [
                'system' => [
                    'label' => 'System',
                    'root' => public_path('img'),
                    'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/img',
                    'directory' => '',
                    'writable' => false,
                ],
                'admin' => [
                    'label' => 'Admin uploads',
                    'disk' => env('PAGE_BLOCKS_DISK', 'public'),
                    'directory' => '',
                    'exclude' => [],
                    'writable' => true,
                ],
            ],
        ],
        'max_size' => 5120,
        'image_max_size' => 5120,
        'image_preview_height' => '220px',
        'image_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'video_max_size' => 51200,
        'video_mime_types' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'file_max_size' => 10240,
        'file_mime_types' => [
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],
    'fields' => [
        'rich_text' => [
            'resizable' => true,
            'resize_direction' => 'vertical',
            'min_height' => '12rem',
            'table_limit' => 120,
            'table_line_clamp' => 2,
            'table_placeholder' => '—',
        ],
    ],
    'generator' => [
        'namespace' => 'App\\PageBlocks',
        'path' => app_path('PageBlocks'),
        'provider_path' => app_path('Providers/AppServiceProvider.php'),
        'view_path' => resource_path('views/page-blocks'),
        'view_namespace' => 'page-blocks',
        'admin_model' => [
            'model_namespace' => 'App\\Models',
            'resource_namespace' => 'App\\Filament\\Resources',
            'resource_path' => app_path('Filament/Resources'),
            'policy_namespace' => 'App\\Policies',
            'policy_path' => app_path('Policies'),
            'permissions_path' => app_path('Filament/Permissions'),
            'panel' => 'admin',
            'modal_width' => '5xl',
        ],
    ],
    'seeders' => [
        'general_info' => [
            'enabled' => true,
            'data' => [
                'email' => 'info@example.com',
                'phone' => '+1 (305) 555-0123',
                'address' => '123 Main Street, Miami, FL 33101',
            ],
            'images' => [],
            'rich_text' => [],
        ],
        'menus' => [
            'header' => 'Header',
            'footer' => 'Footer',
            'admin' => 'Admin',
        ],
        'demo_users' => [
            'enabled' => true,
            'allow_production' => false,
            'model' => 'App\\Models\\User',
            'users' => [
                ['name' => 'dann', 'email' => 'admin@test.com', 'password' => '123', 'role' => 'admin', 'system' => true],
                ['name' => 'manager', 'email' => 'manager@test.com', 'password' => '123', 'role' => 'manager', 'system' => true],
            ],
        ],
    ],
    'legacy_import' => [
        'types' => [],
    ],
];
