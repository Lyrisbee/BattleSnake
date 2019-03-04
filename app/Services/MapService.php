<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Routing\ResponseFactor;

use App\Enums\Snake;

class MapService
{

	/**
	 * Next Best Move
	 *
	 * @var string
	*/
	protected $bestChoice = Snake::DefaultChoice;

	/**
	 * this game id
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * current turn
	 *
	 * @var int
	 */
	protected $trun;

	/**
	 * Map
	 *
	 * @var array
	 */
	protected $map;

	/**
	 * Snakes Info
	 *
	 * @var array
	 */
	protected $snakes;

	/**
	 * Food
	 *
	 * @var array
	 */

	/**
	 * Map Height
	 */
	protected $height;

	/**
	 * Map Width
	 */
	protected $width;

	/**
	 * Create a turn of battle
	 *
	 * @param array $data
	 * @return void
	 */
	public function game(array $data)
	{
		$this->id = $data['game']['id'];
		$this->turn = $data['turn'];

		$this->setMap($data['board']);

		$this->calculateBestMove($data['you']);
	}

	private function setMap(array $board)
	{
		$this->initMap($board['height'], $board['width']);

		$this->setSnakes($board['snakes']);

		$this->setFood($board['food']);
	}

	/**
	 * Initialize Map
	 *
	 * @param integer $x
	 * @param integer $y
	 * @return void
	 */
	private function initMap(int $x, int $y)
	{
		$this->map = array();

		$this->width = $x;
		$this->height = $y;

		$this->map[-1] = array_fill(-1, $x + 2, 'x');
		while($y--)
		{
			$this->map[] = array(-1 => 'x') + array_fill(0, $x, 0) + array($x => 'x');
		}
		$this->map[] = array_fill(-1, $x + 2, 'x');

	}

	/**
	 * Set Food Locate
	 *
	 * @param array $food
	 * @return void
	 */
	private function setFood(array $food)
	{
		foreach($food as $locale)
		{
			$x = $locale['x'];
			$y = $locale['y'];

			$this->map[$x][$y] = Snake::Food;
		}
	}

	/**
	 * Set Snakes
	 *
	 * @param array $snakes
	 * @return void
	 */
	private function setSnakes(array $snakes)
	{
		foreach($snakes as $snake)
		{
			$id = $snake['id'];
			$this->snakes[$id]['length'] = count($snake['body']);
			$length = count($snake['body']);
			foreach(array_unique($snake['body'], SORT_REGULAR) as $ind => $pos)
			{
				$x = $pos['x'];
				$y = $pos['y'];

				if ($ind === 0)
				{
					$this->map[$x][$y] = Snake::Head;
				}
				else if ($ind === $length - 1)
				{
					$this->map[$x][$y] = Snake::Tail;
				}
				else
				{
					$this->map[$x][$y] = $snake['name'];
				}
			}
		}
	}

	/**
	 * Calculate Best Move
	 *
	 * @param array $mySnake
	 * @return void
	 */
	private function calculateBestMove(array $mySnake)
	{
		$head = $mySnake['body'][0];

		$roads = $this->whereCanAccess($head);
		$this->logger('road: '. json_encode($roads));
		$score = array();
		$halfLength = \ceil(\count($mySnake['body'])/2);
		$step = ($halfLength > Snake::MaxDepthOfDFS) ? Snake::MaxDepthOfDFS : $halfLength;
		foreach ($roads as $road => $bool)
		{
			$score[$road] = $this->ScoreDFS($this->map, $mySnake, $road, Snake::MaxDepthOfDFS);
		}

		$this->getBestChoice($score);
	}

	/**
	 * half body step
	 *
	 * @return void
	 */
	private function ScoreDFS($map, $snake, $road, $step)
	{
		$head = $snake['body'][0];
		list($x, $y) = $this->getNextStep($head['x'], $head['y'], $road);
		list($curMap, $curSnake) = $this->getNewMapBody($map, $snake, ['x' => $x, 'y' => $y]);

		$curHead = $curSnake['body'][0];
		$nexts = $this->whereCanAccess($curHead);

		$info = array(
			'isFood' => $this->map[$x][$y] === Snake::Food,
			'isNearHead' => $this->isNearHead($x, $y),
			'numOfNextAccess' => \count($nexts)
		);

		$score = $this->getScore($info);

		if ($step <= 0 || \count($nexts) === 0)
		{
			return array(
				'score' => $score,
				'minusCount' => ($score <= 0) ? 1 : 0,
				'count' => 1
			);
		}

		$dfs = array();
		foreach ($nexts as $next => $bool)
		{
			$dfs[] = $this->ScoreDFS($curMap, $curSnake, $next, $step - 1);
		}

		$maxScore = -999999;
		$minusCount = 0;
		$totalCount = 0;
		foreach ($dfs as $val)
		{
			$maxScore = max($maxScore, $score + Snake::DepthFilter * $val['score']);
			$minusCount += $val['minusCount'];
			$totalCount += $val['count'];
		}

		return array(
			'score' => $maxScore,
			'minusCount' => $minusCount,
			'count' => $totalCount
		);
	}

	/**
	 * get next map and body
	 *
	 * @param array $map
	 * @param array $body
	 * @param array $newPos
	 * @return void
	 */
	private function getNewMapBody(array $map, array $snake, array $newPos)
	{
		$body = $snake['body'];
		$snakeLength = \count(array_unique($body, SORT_REGULAR));
		$length = \count($body);
		$bJustEatFood = $length !== $snakeLength;
		$oldHead = $body[0];
		$oldTail = $body[$length - 1];
		$newTail = $body[$length - 2];

		//just eat food, tail would not move
		if ($bJustEatFood)
		{
			$map[$oldHead['x']][$oldHead['y']] = $snake['name'];
			$map[$newPos['x']][$newPos['y']] = Snake::Head;
			array_shift($body);
			array_unshift($body, $newPos);
		}
		else
		{
			$map[$oldHead['x']][$oldHead['y']] = $snake['name'];
			$map[$oldTail['x']][$oldTail['y']] = Snake::Road;

			$map[$newPos['x']][$newPos['y']] = Snake::Head;
			$map[$newTail['x']][$newTail['y']] = Snake::Tail;

			array_pop($body);
			array_unshift($body, $newPos);
		}

		$snake['body'] = $body;

		return array($map, $snake);
	}

	/**
	 * Get Best Move
	 *
	 * @return Illuminate\Contracts\Routing\ResponseFactor
	 */
	public function getMove()
	{
		return response()->json(
			[
				'move' => $this->bestChoice
			]
		);
	}

	/**
	 * Check if only way to go
	 *
	 * @param array $head
	 * @return array
	 */
	private function whereCanAccess(array $head)
	{
		$x = $head['x'];
		$y = $head['y'];

		return array_filter(array(
			'up' => $this->isAccess($x, $y-1),
			'down' => $this->isAccess($x, $y+1),
			'left' => $this->isAccess($x-1, $y),
			'right' => $this->isAccess($x+1, $y)
		));
	}

	/**
	 * check the way access
	 *
	 * @param integer $x
	 * @param integer $y
	 * @return boolean
	 */
	private function isAccess(int $x, int $y)
	{
		return is_int($this->map[$x][$y]) || $this->map[$x][$y] === Snake::Tail;
	}

	/**
	 * * checkout next step near enemy
	 *
	 * @param array $head
	 * @param string $direction
	 * @return void
	 */
	private function isNearHead(int $x, int $y)
	{
		return \count(
			array_filter(
				array(
					$this->map[$x - 1][$y] === Snake::Head,
					$this->map[$x + 1][$y] === Snake::Head,
					$this->map[$x][$y - 1] === Snake::Head,
					$this->map[$x][$y + 1] === Snake::Head
				)
			)
		) > 1;

	}

	/**
	 * Count next step access
	 *
	 * @param integer $x
	 * @param integer $y
	 * @return boolean
	 */
	private function countNextStepAccess(array $map, int $x, int $y)
	{
		return count(
			array_filter(
				array(
					is_int($newMap[$x - 1][$y]),
					is_int($newMap[$x + 1][$y]),
					is_int($newMap[$x][$y - 1]),
					is_int($newMap[$x][$y + 1])
				)
			)
		);
	}

	/**
	 * Get Next Step position x, y
	 *
	 * @param integer $x
	 * @param integer $y
	 * @param string $direction
	 * @return array(x, y)
	 */
	private function getNextStep(int $x, int $y, string $direction)
	{
		switch($direction)
		{
			case 'up':
				return array($x, $y - 1);
			case 'down':
				return array($x, $y + 1);
			case 'left':
				return array($x - 1, $y);
			case 'right':
				return array($x + 1, $y);
			default:
				return array($x, $y);
		}
	}

	/**
	 * get score
	 *
	 * @param array $info
	 * @return void
	 */
	private function getScore(array $info)
	{
		$score = 100;
		$score += ($info['isNearHead']) ? (Snake::IsNearHeadScore) : 0;
		$score += ($info['isFood']) ? (Snake::IsFoodScore) : 0;
		$score += ($info['numOfNextAccess'] === 0) ? (Snake::DeathRoad) : 0;

		return $score;
	}

	/**
	 * find best way with score
	 *
	 * @param array $score
	 * @return void
	 */
	private function getBestChoice(array $score)
	{
		$this->logger('score: '.json_encode($score));
		$maxScore = -999999;
		$bestChoice = $this->bestChoice;
		foreach($score as $road => $info)
		{
			$calScore = $info['score'] * (1 + $info['minusCount'] / $info['count']);

			if ($calScore > $maxScore)
			{
				$maxScore = $calScore;
				$bestChoice = $road;
			}
			else if ($calScore === $maxScore)
			{
				$rand = rand(0, 1);
				if ($rand === 1)
				{
					$bestChoice = $road;
				}
			}
		}
		$this->bestChoice = $bestChoice;
	}

	/**
	 * Log
	 *
	 * @param string $message
	 * @return void
	 */
	private function logger(string $message)
	{
		Log::info("[Game ID: {$this->id}, Turn: {$this->turn}]: ". $message);
	}
}