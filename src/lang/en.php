<?php

return [
	// Navigation
	'nav.home'                  => 'Home',
	'nav.return'                => 'Return',
	'nav.catalog'               => 'Catalog',
	'nav.refresh'               => 'Refresh',
	'nav.bottom'                => 'Bottom',
	'nav.top'                   => 'Top',
	'nav.hidden'                => 'Hidden',
	'nav.gallery'               => 'Gallery',
	'nav.settings'              => 'Settings',
	'nav.manage'                => 'Manage',
	'nav.previous'              => 'Previous',
	'nav.next'                  => 'Next',

	// Post form
	'form.name'                 => 'Name',
	'form.email'                => 'E-mail',
	'form.mod_options'          => 'Mod options',
	'form.capcode'              => 'Capcode',
	'form.subject'              => 'Subject',
	'form.submit'               => 'Submit',
	'form.message'              => 'Message',
	'form.captcha'              => 'CAPTCHA',
	'form.board'                => 'Board',
	'form.file'                 => 'File',
	'form.draw'                 => 'Draw',
	'form.file_options'         => 'File options',
	'form.spoiler'              => 'Spoiler',
	'form.no_filename'          => 'No filename',
	'form.embed'                => 'Embed',
	'form.password'             => 'Password',
	'form.supported_files'      => 'Supported file types are :types.',
	'form.supported_embeds'     => 'Supported embed types are :types.',
	'form.max_filesize'         => 'Maximum file size allowed is :size.',
	'form.thumb_info'           => 'Images greater than :size will be thumbnailed.',

	// Thread / Posts
	'post.posting_mode'         => 'Posting mode:',
	'post.reply'                => 'Reply',
	'post.truncated'            => 'Post truncated. Click reply to view.',
	'post.replies_omitted'      => ':count replies omitted. Click reply to view.',
	'post.hidden'               => 'Post hidden',
	'post.stickied'             => 'Stickied',
	'post.locked'               => 'Locked',

	// Delete form
	'delete.title'              => 'Delete Post',
	'delete.password'           => 'Password',
	'delete.submit'             => 'Delete',

	// Report form
	'report.notice'             => 'NOTICE',
	'report.info'               => 'Reporting post :post_id on board /:board_id/.',
	'report.warning'            => 'Submitting false or misclassified reports will result in a ban.',
	'report.type'               => 'Type',

	// Board filter
	'filter.toggle'             => 'Filter boards',
	'filter.apply'              => 'Apply',
	'filter.reset'              => 'Reset',

	// Home page
	'home.boards'               => 'Boards',
	'home.stats'                => 'Stats',
	'home.rules'                => 'Rules',
	'home.total_posts'          => 'Total Posts:',
	'home.current_posts'        => 'Current Posts:',
	'home.unique_posters'       => 'Unique Posters:',
	'home.imported_posts'       => 'Imported Posts:',
	'home.current_files'        => 'Current Files:',
	'home.active_content'       => 'Active Content:',

	// Manage
	'manage.title'              => 'Manage',
	'manage.login'              => 'Login',
	'manage.log_in'             => 'Log In',
	'manage.username'           => 'Username',
	'manage.password'           => 'Password',
	'manage.rebuild'            => 'Rebuild',
	'manage.refresh'            => 'Refresh',
	'manage.import'             => 'Import',
	'manage.accounts'           => 'Accounts',
	'manage.logs'               => 'Logs',
	'manage.csam_hashes'        => 'CSAM Hashes',
	'manage.bans'               => 'Bans',
	'manage.posts'              => 'Posts',
	'manage.threads'            => 'Threads',
	'manage.reports'            => 'Reports',
	'manage.logout'             => 'Log out',
	'manage.return'             => 'Return',
	'manage.status'             => 'STATUS',
	'manage.selected'           => 'Selected',
	'manage.delete_post'        => 'Delete (post)',
	'manage.ban_ip'             => 'Ban (ip)',
	'manage.ban_minutes'        => 'Ban minutes',
	'manage.ban_reason'         => 'Ban reason',
	'manage.mark_csam'          => 'Mark as CSAM',
	'manage.toggle_lock'        => 'Lock/unlock (thread)',
	'manage.toggle_sticky'      => 'Sticky/unsticky (thread)',
	'manage.management_iface'   => 'Management interface',
	'manage.approve_report'     => 'Approve (report)',
	'manage.source_db'          => 'Source database',
	'manage.source_table'       => 'Source table',
	'manage.target_board'       => 'Target board',
	'manage.db_name'            => 'DB name',
	'manage.db_user'            => 'DB user',
	'manage.db_pass'            => 'DB pass',
	'manage.table_name'         => 'TABLE name',
	'manage.table_type'         => 'TABLE type',
	'manage.board_id'           => 'BOARD id',

	// Manage table headers
	'manage.th.id'              => 'id',
	'manage.th.post_id'         => 'post_id',
	'manage.th.parent_id'       => 'parent_id',
	'manage.th.board_id'        => 'board_id',
	'manage.th.ip'              => 'ip',
	'manage.th.deleted'         => 'deleted',
	'manage.th.imported'        => 'imported',
	'manage.th.preview'         => 'preview',
	'manage.th.timestamp'       => 'timestamp',
	'manage.th.expire'          => 'expire',
	'manage.th.reason'          => 'reason',
	'manage.th.message'         => 'message',
	'manage.th.username'        => 'username',
	'manage.th.role'            => 'role',
	'manage.th.lastactive'      => 'lastactive',
	'manage.th.type'            => 'type',
	'manage.th.post_ip'         => 'post_ip',
	'manage.th.post_deleted'    => 'post_deleted',
	'manage.th.post_imported'   => 'post_imported',

	// Bans page
	'bans.title'                => 'Bans',
	'bans.subtitle'             => 'Public list of latest bans',
	'bans.heading'              => 'Bans (last 2 weeks)',

	// Logs page
	'logs.title'                => 'Logs',
	'logs.subtitle'             => 'Public list of latest logs',
	'logs.heading'              => 'Logs (last 2 weeks)',

	// Error page
	'error.title'               => 'Error',
	'error.fallback'            => 'something went wrong...',

	// File info
	'file.file'                 => 'File:',
	'file.embed'                => 'Embed:',
	'file.embedded_url'         => 'Embedded URL',

	// Style picker
	'style.label'               => 'Style',
];
