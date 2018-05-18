<?php

namespace  hcp\search\components;

use Elastica\Client;
use Elastica\Query\MatchAll;
use Elastica\Query\Match;
use Elastica\QueryBuilder\DSL\Aggregation;
use Elastica\Search;
use Elastica\Query;
use Elastica\Aggregation\Terms;
use Elastica\Query\Term;
use yii\helpers\ArrayHelper;
use Elastica\Query\BoolQuery;
use Yii;

class GameExporter
{
    /**
     * @var Search
     */
    protected $search;


    public function __construct(Client $client)
    {
        $search = new Search($client);
        $search
        ->addIndex('idx_hcp_game')
        ->addType('game')
        ;
        $this->search = $search;
    }

    public function export()
    {
        $games["all"] =  $this->groupByFirstLetter();
        $games['hots'] = $this->getHotGames();

        return $games;
    }

    /**
     * 获取全部游戏
     *
     * @return array
     */
    private function getAllGames()
    {
        $boolQuery = new BoolQuery();
        $boolQuery->addMust([
            new Term(["isdel" => 0]),
            new Term(['isshow' => 1]),
        ]);

        $query = new Query();
        $query
        ->setSize(5000)
        ->setQuery($boolQuery)
        ->setSort([ 'sort' => 'desc', 'for_sale_count' => 'desc', 'id'=> 'asc'])
        ;

        $this->search->setQuery($query);

        $scroll = $this->search->scroll();
        $scroll->rewind();

        $games = [];
        while ($scroll->valid()) {
            $resultSet = $scroll->current();
            foreach ($resultSet as $result) {
                 $games[] =  [
                    "id" => $result->id,
                    "name" => $result->name,
                    "firstletter" => $result->firstletter,
                    "spelling" => $result->spelling,
                    "sort" => $result->sort,
                    "ishot" => $result->ishot,
                    ];
            }

            $scroll->next();
        }

        return $games;
    }

    /**
     * 以首字分组，每个分组以正出售量倒序排序,并将热门游戏置于分组头部
     *
     * @param  array  $games
     * @return array
     */
    private function groupByFirstLetter()
    {
        $games = $this->getAllGames();

         $groupedGames = [];

        foreach ($games as $game) {
             unset($game['ishot']) ;
             $game['ishot']  = !empty($game['sort']) ? "1": "0";

             $firstLetter = $game['firstletter'];

            if (isset($groupedGames[$firstLetter])) {
                array_push($groupedGames[$firstLetter], $game);
            } else {
                $groupedGames[$firstLetter][] = $game;
            }
        }

        ksort($groupedGames);

        return $groupedGames;
    }

    private function getHotGames()
    {
        $boolQuery = new BoolQuery();
        $boolQuery->addMust([
            new Term(["isdel" => 0]),
            new Term(['isshow' => 1]),
            new Term(['ishot' => 1]),
        ]);

        $hotQuery = (new Query())
        ->setSize(5000)
        ->setQuery($boolQuery)
        ->setSort([ 'sort' => 'desc', 'for_sale_count' => 'desc', 'id'=> 'asc'])
        ;

        $this->search->setQuery($hotQuery);
        $resultSet = $this->search->search();

        $hotGames = [];
        foreach ($resultSet as $result) {
            $hotGames[] = [
                "id" => $result->id,
                "name" => $result->name,
                "spelling" => $result->spelling,
                "pic" => empty($result->pic)? "": Yii::$app->params['ImageServerHost'].$result->pic,
            ];
        }

        return $hotGames;
    }
}
