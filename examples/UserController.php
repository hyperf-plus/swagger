<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use HPlus\Route\Annotation\ApiController;
use HPlus\Route\Annotation\GetApi;
use HPlus\Route\Annotation\PostApi;
use HPlus\Route\Annotation\PutApi;
use HPlus\Route\Annotation\DeleteApi;
use HPlus\Route\Annotation\ApiResponse;
use HPlus\Route\Annotation\ApiResponseExample;
use HPlus\Route\Annotation\RequestBody;
use HPlus\Validate\Annotations\RequestValidation;
use HPlus\Swagger\Annotation\ApiDefinition;

/**
 * 用户管理控制器 - RESTful增强版
 * 
 * 展示RESTful规则自动生成特性：
 * 1. 控制器自动前缀：UserController → /api/users（复数）
 * 2. RESTful方法映射：方法名+HTTP动词 → 标准路径
 * 3. 用户设置优先：设置了prefix/path就用设置的
 * 4. 必须注解控制：只有加了路由注解的方法才能访问
 * 
 * RESTful自动映射示例：
 * - GET + index() → GET /api/users
 * - GET + show() → GET /api/users/{id}
 * - POST + create() → POST /api/users
 * - PUT + update() → PUT /api/users/{id}
 * - DELETE + delete() → DELETE /api/users/{id}
 */
#[ApiController(
    // prefix 不设置：自动生成 /api/users (RESTful复数)
    // 如果设置了：prefix: '/api/v1/user'，就用你设置的
    tag: 'User Management',
    description: '用户管理相关接口'
)]
class UserController
{
    public function __construct(
        private RequestInterface $request,
        private ResponseInterface $response
    ) {}

    /**
     * 获取用户列表
     * RESTful自动路径：GET /api/users (因为方法名是index)
     */
    #[GetApi(
        // path 不设置：根据方法名index自动生成空路径
        // 如果设置了：path: '/list'，就是 GET /api/users/list
        summary: '获取用户列表',
        description: '支持分页和筛选的用户列表接口'
    )]
    #[RequestValidation(rules: [
        'page|页码' => 'integer|min:1|default:1',
        'size|每页数量' => 'integer|min:1|max:100|default:20',
        'keyword|搜索关键词' => 'string|max:100',
        'status|用户状态' => 'in:active,inactive,pending'
    ])]
    public function index()  // RESTful标准方法名
    {
        $page = (int)$this->request->query('page', 1);
        $size = (int)$this->request->query('size', 20);
        
        // 模拟数据
        $users = [];
        for ($i = 1; $i <= $size; $i++) {
            $id = ($page - 1) * $size + $i;
            $users[] = [
                'id' => $id,
                'username' => "user{$id}",
                'email' => "user{$id}@example.com",
                'status' => 'active',
                'created_at' => '2023-01-01 00:00:00'
            ];
        }

        return [
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'size' => $size,
                'total' => 1000
            ]
        ];
    }

    /**
     * 获取用户详情
     * RESTful自动路径：GET /api/users/{id} (因为方法名是show)
     */
    #[GetApi(
        // path 不设置：根据方法名show自动生成 /{id}
        summary: '获取用户详情'
    )]
    public function show(int $id)  // RESTful标准方法名
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        return [
            'id' => $id,
            'username' => "user{$id}",
            'email' => "user{$id}@example.com",
            'profile' => [
                'nickname' => "用户{$id}",
                'avatar' => 'https://example.com/avatar.jpg',
                'bio' => '这是用户简介'
            ],
            'status' => 'active',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00'
        ];
    }

    /**
     * 创建用户
     * RESTful自动路径：POST /api/users (因为方法名是create)
     */
    #[PostApi(
        // path 不设置：根据方法名create自动生成空路径
        summary: '创建用户'
    )]
    #[RequestValidation(
        rules: [
            'username|用户名' => 'required|string|min:3|max:20',
            'email|邮箱' => 'required|email|max:100',
            'password|密码' => 'required|string|min:6|max:32',
            'profile|个人资料' => 'array',
            'profile.nickname|昵称' => 'string|max:50',
            'profile.bio|个人简介' => 'string|max:200'
        ],
        dateType: 'json'
    )]
    public function create()  // RESTful标准方法名
    {
        $data = $this->request->getParsedBody();
        
        // 模拟创建
        $user = [
            'id' => rand(1000, 9999),
            'username' => $data['username'],
            'email' => $data['email'],
            'profile' => $data['profile'] ?? [],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->response->json($user)->withStatus(201);
    }

    /**
     * 更新用户
     * RESTful自动路径：PUT /api/users/{id} (因为方法名是update)
     */
    #[PutApi(
        // path 不设置：根据方法名update自动生成 /{id}
        summary: '更新用户信息'
    )]
    #[RequestValidation(
        rules: [
            'username|用户名' => 'string|min:3|max:20',
            'email|邮箱' => 'email|max:100',
            'profile|个人资料' => 'array',
            'profile.nickname|昵称' => 'string|max:50',
            'profile.bio|个人简介' => 'string|max:200',
            'status|状态' => 'in:active,inactive,pending'
        ],
        dateType: 'json'
    )]
    public function update(int $id)  // RESTful标准方法名
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        $data = $this->request->getParsedBody();
        
        return [
            'id' => $id,
            'username' => $data['username'] ?? "user{$id}",
            'email' => $data['email'] ?? "user{$id}@example.com",
            'profile' => $data['profile'] ?? [],
            'status' => $data['status'] ?? 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 删除用户
     * RESTful自动路径：DELETE /api/users/{id} (因为方法名是delete)
     */
    #[DeleteApi(
        // path 不设置：根据方法名delete自动生成 /{id}
        summary: '删除用户'
    )]
    public function delete(int $id)  // RESTful标准方法名
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        // 模拟软删除
        return $this->response->withStatus(204);
    }

    /**
     * 获取用户状态
     * RESTful自动路径：GET /api/users/{id}/state (资源子操作)
     */
    #[GetApi(
        // path 不设置：根据方法名state自动生成 /{id}/state
        summary: '获取用户状态'
    )]
    public function state(int $id)  // RESTful资源子操作
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        return [
            'id' => $id,
            'state' => 'active',
            'last_active' => date('Y-m-d H:i:s'),
            'login_count' => rand(10, 100),
            'is_online' => (bool)rand(0, 1)
        ];
    }

    /**
     * 启用用户
     * RESTful自动路径：POST /api/users/{id}/enable (资源子操作)
     */
    #[PostApi(
        summary: '启用用户'
    )]
    public function enable(int $id)  // RESTful资源子操作
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        return [
            'id' => $id,
            'status' => 'active',
            'enabled_at' => date('Y-m-d H:i:s'),
            'message' => '用户已启用'
        ];
    }

    /**
     * 禁用用户
     * RESTful自动路径：POST /api/users/{id}/disable (资源子操作)
     */
    #[PostApi(
        summary: '禁用用户'
    )]
    public function disable(int $id)  // RESTful资源子操作
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        return [
            'id' => $id,
            'status' => 'inactive',
            'disabled_at' => date('Y-m-d H:i:s'),
            'message' => '用户已禁用'
        ];
    }

    /**
     * 获取用户权限
     * RESTful自动路径：GET /api/users/{id}/permissions (资源子操作)
     */
    #[GetApi(
        summary: '获取用户权限'
    )]
    public function permissions(int $id)  // RESTful资源子操作
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        return [
            'user_id' => $id,
            'permissions' => [
                'users.view',
                'users.create',
                'users.update',
                'posts.view',
                'posts.create'
            ],
            'roles' => ['editor', 'moderator']
        ];
    }

    /**
     * 获取用户历史记录
     * RESTful自动路径：GET /api/users/{id}/history (资源子操作)
     */
    #[GetApi(
        summary: '获取用户历史记录'
    )]
    public function history(int $id)  // RESTful资源子操作
    {
        if ($id <= 0) {
            return $this->response->json(['error' => 'Invalid ID'])->withStatus(400);
        }

        return [
            'user_id' => $id,
            'history' => [
                [
                    'action' => 'login',
                    'timestamp' => '2023-12-01 10:00:00',
                    'ip' => '192.168.1.1'
                ],
                [
                    'action' => 'update_profile',
                    'timestamp' => '2023-12-01 10:30:00',
                    'changes' => ['nickname' => 'new_name']
                ],
                [
                    'action' => 'logout',
                    'timestamp' => '2023-12-01 11:00:00'
                ]
            ]
        ];
    }

    /**
     * 搜索用户
     * RESTful自动路径：GET /api/users/search (因为方法名是search)
     */
    #[GetApi(
        // path 不设置：根据方法名search自动生成 /search
        summary: '搜索用户'
    )]
    #[RequestValidation(rules: [
        'q|搜索关键词' => 'required|string|min:1|max:100',
        'type|搜索类型' => 'in:username,email,nickname',
        'limit|返回数量' => 'integer|min:1|max:50|default:10'
    ])]
    public function search()  // RESTful扩展方法
    {
        $keyword = $this->request->query('q');
        $type = $this->request->query('type', 'username');
        $limit = (int)$this->request->query('limit', 10);

        // 模拟搜索结果
        $results = [];
        for ($i = 1; $i <= $limit; $i++) {
            $results[] = [
                'id' => $i,
                'username' => "match_user_{$i}",
                'email' => "match{$i}@example.com",
                'highlight' => $keyword
            ];
        }

        return [
            'keyword' => $keyword,
            'type' => $type,
            'results' => $results,
            'total' => count($results)
        ];
    }

    /**
     * 批量操作
     * RESTful自动路径：POST /api/users/batch (因为方法名是batch)
     */
    #[PostApi(
        // path 不设置：根据方法名batch自动生成 /batch
        summary: '批量操作用户'
    )]
    #[RequestValidation(
        rules: [
            'action|操作类型' => 'required|in:enable,disable,delete',
            'ids|用户ID列表' => 'required|array|min:1',
            'ids.*|用户ID' => 'integer|min:1'
        ],
        dateType: 'json'
    )]
    public function batch()  // RESTful扩展方法
    {
        $data = $this->request->getParsedBody();
        
        return [
            'action' => $data['action'],
            'processed_ids' => $data['ids'],
            'success_count' => count($data['ids']),
            'message' => '批量操作完成'
        ];
    }

    /**
     * 导出用户数据
     * RESTful自动路径：GET /api/users/export (因为方法名是export)
     */
    #[GetApi(
        summary: '导出用户数据'
    )]
    #[RequestValidation(rules: [
        'format|导出格式' => 'in:csv,excel,json|default:csv',
        'fields|导出字段' => 'array',
        'fields.*' => 'in:id,username,email,status,created_at'
    ])]
    public function export()  // RESTful扩展方法
    {
        $format = $this->request->query('format', 'csv');
        $fields = $this->request->query('fields', ['id', 'username', 'email']);
        
        return [
            'download_url' => '/downloads/users.' . $format,
            'format' => $format,
            'fields' => $fields,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ];
    }

    /**
     * 自定义方法名的例子
     * 非RESTful方法名：getUserActiveCount → GET /api/users/get-user-active-count
     */
    #[GetApi(
        // 非标准RESTful方法名，会自动转换为 /get-user-active-count
        summary: '获取活跃用户数量'
    )]
    public function getUserActiveCount()
    {
        return [
            'active_count' => 567,
            'total_count' => 1000,
            'percentage' => 56.7
        ];
    }

    /**
     * 用户自定义路径的例子
     * 用户设置了path，优先使用用户的设置
     */
    #[GetApi(
        path: '/statistics',  // 用户手动设置了path，不会自动生成
        summary: '用户统计信息'
    )]
    public function someMethodName()  // 方法名不重要了，因为path已指定
    {
        return [
            'total_users' => 1000,
            'active_users' => 567,
            'inactive_users' => 433
        ];
    }

    /**
     * 智能参数识别示例1：单个ID参数
     * 自动生成：GET /api/users/{id}/custom-action
     */
    #[GetApi(
        summary: '自定义操作（智能路径）'
    )]
    public function customAction(int $id)  // 智能识别ID参数
    {
        return [
            'id' => $id,
            'action' => 'custom',
            'result' => 'success'
        ];
    }

    /**
     * 智能参数识别示例2：多个参数
     * 自动生成：GET /api/users/{userId}/posts/{postId}
     */
    #[GetApi(
        summary: '获取用户的特定文章'
    )]
    public function posts(int $userId, int $postId)  // 智能识别多个参数
    {
        return [
            'user_id' => $userId,
            'post_id' => $postId,
            'title' => "User {$userId}'s Post {$postId}",
            'content' => 'Post content here...'
        ];
    }

    /**
     * 智能参数识别示例3：过滤器模式
     * 自动生成：GET /api/users/get-by-email/{email}
     */
    #[GetApi(
        summary: '通过邮箱查找用户'
    )]
    public function getByEmail(string $email)  // 识别为过滤器模式
    {
        return [
            'email' => $email,
            'user' => [
                'id' => 123,
                'username' => 'john_doe',
                'email' => $email
            ]
        ];
    }

    /**
     * 智能参数识别示例4：比较操作
     * 自动生成：POST /api/users/{id}/compare-with/{otherId}
     */
    #[PostApi(
        summary: '比较两个用户'
    )]
    public function compareWith(int $id, int $otherId)  // 智能组合路径
    {
        return [
            'user1' => $id,
            'user2' => $otherId,
            'differences' => [
                'created_at' => '2 days',
                'post_count' => 5,
                'follower_count' => 100
            ]
        ];
    }

    /**
     * 智能参数识别示例5：复杂参数
     * 自动生成：GET /api/users/{id}/analyze-activity/{year}/{month}
     */
    #[GetApi(
        summary: '分析用户活动'
    )]
    public function analyzeActivity(int $id, int $year, int $month)  // 多个参数
    {
        return [
            'user_id' => $id,
            'period' => "{$year}-{$month}",
            'login_count' => 25,
            'post_count' => 10,
            'comment_count' => 50
        ];
    }

    /**
     * 智能参数识别示例6：无参数方法
     * 自动生成：GET /api/users/get-online-count
     */
    #[GetApi(
        summary: '获取在线用户数'
    )]
    public function getOnlineCount()  // 无参数，简单路径
    {
        return [
            'online_count' => 128,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    // 注意：这些方法没有路由注解，不会对外暴露！
    public function helperMethod()
    {
        return 'This method has no route annotation, so it\'s not accessible';
    }
    
    private function internalMethod()
    {
        return 'Private methods are never exposed';
    }
}

/**
 * 另一个控制器示例：展示复数化和版本控制
 */
#[ApiController(
    // 不设置prefix：自动生成 /api/categories (category复数化)
)]
class CategoryController
{
    #[GetApi]
    public function index()
    {
        // 自动路径：GET /api/categories
        return ['categories' => []];
    }
}

/**
 * API版本控制示例
 */
namespace App\Controller\Api\V2;

#[ApiController(
    // 不设置prefix：自动生成 /api/v2/products
)]
class ProductController
{
    #[GetApi]
    public function index()
    {
        // 自动路径：GET /api/v2/products
        return ['products' => []];
    }
}

/**
 * 用户完全自定义示例
 */
#[ApiController(
    prefix: '/custom/path',  // 用户设置了，就用这个
    tag: 'Custom API'
)]
class CustomController
{
    #[GetApi(path: '/special')]  // 用户设置了，就用这个
    public function anything()
    {
        // 最终路径：GET /custom/path/special
        return ['custom' => true];
    }
}

/**
 * 用户数据模型定义
 */
#[ApiDefinition(
    name: 'User',
    type: 'object',
    description: '用户数据模型',
    properties: [
        'id' => ['type' => 'integer', 'description' => '用户ID', 'example' => 1],
        'username' => ['type' => 'string', 'description' => '用户名', 'example' => 'john_doe'],
        'email' => ['type' => 'string', 'format' => 'email', 'description' => '邮箱'],
        'profile' => [
            'type' => 'object',
            'description' => '个人资料',
            'properties' => [
                'nickname' => ['type' => 'string', 'description' => '昵称'],
                'avatar' => ['type' => 'string', 'format' => 'uri', 'description' => '头像URL'],
                'bio' => ['type' => 'string', 'description' => '个人简介']
            ]
        ],
        'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'pending'], 'description' => '状态'],
        'created_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
        'updated_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '更新时间']
    ],
    required: ['id', 'username', 'email', 'status']
)]
class UserSchema {} 