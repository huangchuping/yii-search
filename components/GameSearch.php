<?php

namespace tsy\search\components;

use \Yii;
use Elastica\Query\MatchPhrase;
use Elastica\Query\Match;
use Elastica\Query\FunctionScore;
use Elastica\Search;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Term;
use Elastica\Client;

class GameSearch
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function search($keyword, $page, $size = 15)
    {
        if (empty($keyword)) {
            return [];
        }

        $search = new Search($this->client);
        $search
        ->addIndex('idx_tsy_game')
        ->addType('game');

        $pinyin = new MatchPhrase("name.pinyin", ["query" => $keyword, "boost" => 0.4]);

        $boolQuery = new BoolQuery();
        $boolQuery->addShould([
            $pinyin,
            new MatchPhrase("name", ["query" => $keyword, "boost" => 0.1]),
            new Match("name.standard", $keyword),
        ])
        ->addMust([
            new Term(["isdel" => 0]),
            new Term(['isshow' => 1]),
        ])
        ;
        $boolFilter = new BoolQuery();
        ;
        $functionScore = new FunctionScore();
        $functionScore->addFunction(
            'script_score',
            [
                "script"=> "_score + Math.log1p(doc['for_sale_count'].value)"
            ],
            $boolFilter
        )
        ->setQuery($boolQuery)
        ->setBoostMode(FunctionScore::BOOST_MODE_REPLACE)
        ->setMaxBoost(16)
        ;

        $page = $page <0 ? 1: $page;
        $size = $size <0 ? 15: $size;
        $query = new Query();
        $query
        ->setFrom(($page-1) * $size)
        ->setSize($size)
        ->setSort([ '_score' => 'desc', 'for_sale_count' => 'desc', 'id'=> 'asc'])
        ->setQuery($functionScore);
        ;

        $search->setQuery($query);

        $resultSet = $search->search();

        $games = [];
        foreach ($resultSet as $result) {
            $games[] = [
                "id" => $result->id,
                "name" => $result->name,
                "spelling" => $result->spelling,
                "pic" => empty($result->pic)? "": Yii::$app->params['ImageServerHost'].$result->pic,
            ];
        }

        if (empty($games)) {
            return [];
        }

        return [
             'data' => [
                'list' => $games,
                'page' => [
                     'pagecount' =>  ceil($resultSet->getTotalHits()/$size),
                     'totalcount' => $resultSet->getTotalHits(),
                ]
             ],
        ];
    }
}
