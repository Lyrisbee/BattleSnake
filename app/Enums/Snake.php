<?php

namespace App\Enums;

abstract class Snake {

	const DefaultChoice = 'up';
	//care is_int check
	const Road = 0;
	const Food = 1;
	const Head = 'H';
	const Tail = 'T';

	const MaxDepthOfDFS = 6;

	const DepthFilter = 0.9;

	const IsNearHeadScore = -50;
	const IsFoodScore = 30;
	const DeathRoad = -1000;

}
