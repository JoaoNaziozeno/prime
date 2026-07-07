<?php

return [
    /**
     * Configurações gerais do ERP
     */
    'name' => env('APP_NAME', 'Prime ERP'),
    'version' => '1.0.0',
    
    /**
     * Configurações de Multi-Tenant
     */
    'multi_tenant' => [
        'enabled' => true,
        'driver' => env('TENANT_DRIVER', 'mysql'),
    ],
    
    /**
     * Configurações de Auditoria
     */
    'audit' => [
        'enabled' => true,
        'record_ip' => true,
        'record_user_agent' => true,
    ],
    
    /**
     * Configurações de Cache
     */
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'redis'),
        'ttl' => env('CACHE_TTL', 3600),
    ],
    
    /**
     * Configurações de Storage
     */
    'storage' => [
        'driver' => env('STORAGE_DRIVER', 'minio'),
        'visibility' => 'private',
    ],
    
    /**
     * Configurações de Fila
     */
    'queue' => [
        'driver' => env('QUEUE_CONNECTION', 'redis'),
        'tries' => 3,
        'timeout' => 90,
    ],
    
    /**
     * Permissões Padrão do Sistema
     */
    'default_permissions' => [
        'view' => 'Visualizar',
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Deletar',
        'export' => 'Exportar',
    ],
    
    /**
     * Módulos Disponíveis
     */
    'modules' => [
        'master' => [
            'name' => 'Master',
            'description' => 'Banco de dados central',
        ],
        'tenant' => [
            'name' => 'Tenant',
            'description' => 'Banco de dados do tenant',
        ],
    ],
];
