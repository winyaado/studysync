<?php
/**
 * APIテストケース定義ファイル（読み取り系のみ）
 */

return [
    '検索・一覧' => [
        [
            'test_name' => '検索: 基本クエリ',
            'endpoint' => '/api/content/search_contents',
            'method' => 'GET',
            'params' => ['q' => 'テスト'],
            'expected_status' => 200,
        ],
        [
            'test_name' => '検索: パラメータ付き',
            'endpoint' => '/api/content/search_contents',
            'method' => 'GET',
            'params' => ['q' => 'テスト', 'types' => 'note', 'sort' => 'updated_at', 'order' => 'desc'],
            'expected_status' => 200,
        ],
        [
            'test_name' => '講義検索',
            'endpoint' => '/api/system/search_lectures',
            'method' => 'GET',
            'params' => ['q' => ''],
            'expected_status' => 200,
        ],
    ],
    'コンテンツ取得' => [
        [
            'test_name' => 'マイコンテンツ一覧',
            'endpoint' => '/api/content/my_contents',
            'method' => 'GET',
            'params' => [],
            'expected_status' => 200,
        ],
        [
            'test_name' => 'ライブラリ一覧',
            'endpoint' => '/api/user/get_library_contents',
            'method' => 'GET',
            'params' => [],
            'expected_status' => 200,
        ],
        [
            'test_name' => '問題集編集用取得 (id=1)',
            'endpoint' => '/api/content/get_problem_set_for_edit',
            'method' => 'GET',
            'params' => ['id' => 1],
            'expected_status' => 200,
        ],
        [
            'test_name' => 'ノート編集用取得 (id=1)',
            'endpoint' => '/api/content/get_note_for_edit',
            'method' => 'GET',
            'params' => ['id' => 1],
            'expected_status' => 200,
        ],
        [
            'test_name' => 'フラッシュカード編集用取得 (id=1)',
            'endpoint' => '/api/content/get_flashcard_for_edit',
            'method' => 'GET',
            'params' => ['id' => 1],
            'expected_status' => 200,
        ],
        [
            'test_name' => 'フラッシュカード詳細取得 (id=1)',
            'endpoint' => '/api/content/get_flashcard_details',
            'method' => 'GET',
            'params' => ['id' => 1],
            'expected_status' => 200,
        ],
        [
            'test_name' => '問題集詳細取得 (id=1)',
            'endpoint' => '/api/study/problem_details',
            'method' => 'GET',
            'params' => ['id' => 1],
            'expected_status' => 200,
        ],
        [
            'test_name' => '試験結果取得 (attempt_id=1)',
            'endpoint' => '/api/study/result_details',
            'method' => 'GET',
            'params' => ['attempt_id' => 1],
            'expected_status' => 200,
        ],
    ],
    'プロフィール' => [
        [
            'test_name' => 'ユーザープロフィール取得 (user_id=1)',
            'endpoint' => '/api/user/get_user_profile',
            'method' => 'GET',
            'params' => ['user_id' => 1],
            'expected_status' => 200,
        ],
    ],
    '学習データ' => [
        [
            'test_name' => '試験問題取得 (id=1)',
            'endpoint' => '/api/study/solve_data',
            'method' => 'GET',
            'params' => ['id' => 1],
            'expected_status' => 200,
        ],
        [
            'test_name' => 'フラッシュカード学習データ取得',
            'endpoint' => '/api/study/get_flashcard_study_data',
            'method' => 'GET',
            'params' => ['ids' => '1', 'filter' => 'all'],
            'expected_status' => 200,
        ],
    ],
    '通知・設定' => [
        [
            'test_name' => 'アクティブ通知取得',
            'endpoint' => '/api/system/get_active_informations',
            'method' => 'GET',
            'params' => [],
            'expected_status' => 200,
        ],
        [
            'test_name' => '全通知取得',
            'endpoint' => '/api/admin/get_all_informations',
            'method' => 'GET',
            'params' => [],
            'expected_status' => 200,
        ],
        [
            'test_name' => 'アイコン設定取得',
            'endpoint' => '/api/user/get_identicon_settings',
            'method' => 'GET',
            'params' => [],
            'expected_status' => 200,
        ],
        [
            'test_name' => '生成許可確認',
            'endpoint' => '/api/content/check_creation_allowance',
            'method' => 'GET',
            'params' => [],
            'expected_status' => 200,
        ],
    ],
];

