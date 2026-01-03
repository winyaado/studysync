<?php
/**
 * ルート定義: APIルートと実体ファイル/認証要件の対応表。
 * @return array<string,array{file:string,require_admin:bool}>
 */
return [
	// content
	'content/activate_content' => ['file' => __DIR__ . '/content/activate_content.php',	'require_admin' => false],
	'content/check_creation_allowance' => ['file' => __DIR__ . '/content/check_creation_allowance.php',	'require_admin' => false],
	'content/delete_content' => ['file' => __DIR__ . '/content/delete_content.php',	'require_admin' => false],
	'content/get_flashcard_details' => ['file' => __DIR__ . '/content/get_flashcard_details.php',	'require_admin' => false],
	'content/get_flashcard_for_edit' => ['file' => __DIR__ . '/content/get_flashcard_for_edit.php',	'require_admin' => false],
	'content/get_note_for_edit' => ['file' => __DIR__ . '/content/get_note_for_edit.php',	'require_admin' => false],
	'content/get_problem_set_for_edit' => ['file' => __DIR__ . '/content/get_problem_set_for_edit.php',	'require_admin' => false],
	'content/my_contents' => ['file' => __DIR__ . '/content/my_contents.php',	'require_admin' => false],
	'content/save_flashcard' => ['file' => __DIR__ . '/content/save_flashcard.php',	'require_admin' => false],
	'content/save_note' => ['file' => __DIR__ . '/content/save_note.php',	'require_admin' => false],
	'content/save_problem_set' => ['file' => __DIR__ . '/content/save_problem_set.php',	'require_admin' => false],
	'content/save_rating' => ['file' => __DIR__ . '/content/save_rating.php',	'require_admin' => false],
	'content/search_contents' => ['file' => __DIR__ . '/content/search_contents.php',	'require_admin' => false],

	// study
	'study/get_flashcard_study_data' => ['file' => __DIR__ . '/study/get_flashcard_study_data.php',	'require_admin' => false],
	'study/problem_details' => ['file' => __DIR__ . '/study/problem_details.php',	'require_admin' => false],
	'study/result_details' => ['file' => __DIR__ . '/study/result_details.php',	'require_admin' => false],
	'study/solve_data' => ['file' => __DIR__ . '/study/solve_data.php',	'require_admin' => false],
	'study/submit_exam' => ['file' => __DIR__ . '/study/submit_exam.php',	'require_admin' => false],
	'study/update_flashcard_memory' => ['file' => __DIR__ . '/study/update_flashcard_memory.php',	'require_admin' => false],

	// user
	'user/delete_favorite_identicon' => ['file' => __DIR__ . '/user/delete_favorite_identicon.php',	'require_admin' => false],
	'user/delete_favorite_seed' => ['file' => __DIR__ . '/user/delete_favorite_seed.php',	'require_admin' => false],
	'user/generate_new_identicon_seeds' => ['file' => __DIR__ . '/user/generate_new_identicon_seeds.php',	'require_admin' => false],
	'user/get_follow_lists' => ['file' => __DIR__ . '/user/get_follow_lists.php',	'require_admin' => false],
	'user/get_identicon_settings' => ['file' => __DIR__ . '/user/get_identicon_settings.php',	'require_admin' => false],
	'user/get_library_contents' => ['file' => __DIR__ . '/user/get_library_contents.php',	'require_admin' => false],
	'user/get_user_profile' => ['file' => __DIR__ . '/user/get_user_profile.php',	'require_admin' => false],
	'user/save_favorite_identicon' => ['file' => __DIR__ . '/user/save_favorite_identicon.php',	'require_admin' => false],
	'user/save_favorite_seed' => ['file' => __DIR__ . '/user/save_favorite_seed.php',	'require_admin' => false],
	'user/save_settings' => ['file' => __DIR__ . '/user/save_settings.php',	'require_admin' => false],
	'user/set_active_identicon' => ['file' => __DIR__ . '/user/set_active_identicon.php',	'require_admin' => false],
	'user/set_active_seed' => ['file' => __DIR__ . '/user/set_active_seed.php',	'require_admin' => false],
	'user/toggle_follow' => ['file' => __DIR__ . '/user/toggle_follow.php',	'require_admin' => false],
	'user/toggle_library_content' => ['file' => __DIR__ . '/user/toggle_library_content.php',	'require_admin' => false],

	// system
	'system/get_active_informations' => ['file' => __DIR__ . '/system/get_active_informations.php',	'require_admin' => false],
	'system/google_oauth_callback' => ['file' => __DIR__ . '/util/google_oauth_callback.php',	'require_admin' => false],
	'system/report_content' => ['file' => __DIR__ . '/system/report_content.php',	'require_admin' => false],
	'system/search_lectures' => ['file' => __DIR__ . '/system/search_lectures.php',	'require_admin' => false],

	// admin
	'admin/ban_tenant' => ['file' => __DIR__ . '/admin/ban_tenant.php',	'require_admin' => true],
	'admin/ban_user' => ['file' => __DIR__ . '/admin/ban_user.php',	'require_admin' => true],
	'admin/delete_information' => ['file' => __DIR__ . '/admin/delete_information.php',	'require_admin' => true],
	'admin/get_all_informations' => ['file' => __DIR__ . '/admin/get_all_informations.php',	'require_admin' => true],
	'admin/get_reports' => ['file' => __DIR__ . '/admin/get_reports.php',	'require_admin' => true],
	'admin/get_tenants' => ['file' => __DIR__ . '/admin/get_tenants.php',	'require_admin' => true],
	'admin/get_users' => ['file' => __DIR__ . '/admin/get_users.php',	'require_admin' => true],
	'admin/save_information' => ['file' => __DIR__ . '/admin/save_information.php',	'require_admin' => true],
	'admin/update_report_status' => ['file' => __DIR__ . '/admin/update_report_status.php',	'require_admin' => true],

];
