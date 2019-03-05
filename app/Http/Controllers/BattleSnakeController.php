<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Routing\ResponseFactor;
use App\Services\MapService;

class BattleSnakeController extends Controller
{
	/** @var MapService */
	protected $mapService;

	/**
	 * @param MapService $mapService
	 * @return void
	 */
	public function __construct(MapService $mapService)
	{
		$this->mapService = $mapService;
	}

	/**
	 * Start a new game.
	 *
	 * @return string
	 */
	public function start()
	{
		return response()->json(
			[
				"color" => "#12A2AA",
				"headType" => "bendr",
				"tailType" => "pixel"
			]
		);
	}

	/**
	 * Snake move
	 *
	 * @param Request $request
	 * @return void
	 */
	public function move(Request $request)
	{
		$data = $request->json()->all();

		$this->mapService->game($data);

		return $this->mapService->getMove();
	}

	/**
	 * Active Response
	 *
	 * @return string
	 */
	public function ping()
	{
		return 'Hello';
	}

	/**
	 * End Game
	 *
	 * @param Request $request
	 * @return any
	 */
	public function end()
	{
		return 'Good Bye! World!';
	}

}