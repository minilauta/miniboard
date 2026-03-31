<?php

namespace minichan\models;

enum PostEvent: int
{
	case Moved = 1;
	case DeletedAdmin = 2;
	case DeletedUser = 3;
}

class PostHistory
{
	public int $id;
	public string $board_id;
	public int $post_id;
	public ?int $parent_id;
	public int $event;
	public int $timestamp;
	public ?string $dst_board_id;
	public ?int $dst_post_id;
}
