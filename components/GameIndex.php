<?php

namespace hcp\search\components;

use Yii;
use Elastica\Client;

class GameIndex
{
    private $index = [
                "number_of_shards" => 1,
                "number_of_replicas" => 1,
                "analysis" => [
                      "filter"=> [
                         "pinyin_filter" => [
                            "type" => "pinyin",
                            "keep_separate_first_letter" => false,
                            "keep_full_pinyin" => true,
                            "keep_original" => true,
                            "limit_first_letter_length" => 16,
                            "keep_joined_full_pinyin" => true,
                            "lowercase" => true,
                            "remove_duplicated_term" => true,
                            "none_chinese_pinyin_tokenize" => true,
                            "keep_none_chinese_in_joined_full_pinyin" => true,
                         ]
                      ],
                      "analyzer" => [
                        "autocomplete"=> [
                            "type"=>      "custom",
                            "tokenizer"=> "ik_max_word",
                            "filter"=> [
                                "lowercase",
                                "pinyin_filter"
                            ]
                        ],
                        "pinyin_analyzer" => [
                            "tokenizer" => "hcp_pinyin"
                        ],
                      ],
                      "tokenizer" => [
                        "hcp_pinyin" => [
                            "type" => "pinyin",
                            "keep_first_letter" => true,
                            "keep_separate_first_letter" => false,
                            "keep_full_pinyin" => false,
                            "keep_original" => false,
                            "limit_first_letter_length" => 16,
                            "keep_joined_full_pinyin" => true,
                            "lowercase" => true,
                            "remove_duplicated_term" => true,
                            "keep_none_chinese_in_joined_full_pinyin" => true,
                        ],
                      ]
                ]
             ];

    private $properties = [
        "id"      => [
            "type" => "integer",
        ],
        "firstletter"=> [
            "type" => 'text',
            "fields"=> [
                "keyword"=> [
                  "type"=> "keyword"
                ]
              ]
        ],
        "spelling"=> [
            "type" => 'text',
        ],
        "ishot"=> [
            "type" => 'integer',
        ],
        "isshow"=> [
            "type" => 'integer',
        ],
        "isrecommend"=> [
            "type" => 'integer',
        ],
        "ischarge"=> [
            "type" => 'integer',
        ],
        "isquick"=> [
            "type" => 'integer',
        ],
        "quickchargebegintime"=> [
            "type" => 'date',
            "format" => "yyyy-MM-dd HH:mm:ss",
            "ignore_malformed" => true,
        ],
        "quickchargeendtime"=> [
            "type" => 'date',
            "format" => "yyyy-MM-dd HH:mm:ss",
            "ignore_malformed" =>  true,
        ],
        "isquickcharge"=> [
            "type" => 'integer',
        ],
        "isdel"=> [
            "type" => 'integer',
        ],
        "isdel"=> [
            "type" => 'integer',
        ],
        "ispopularize" => [
            "type" => 'integer',
        ],
        "isfilter_idcard"=> [
            "type" => 'integer',
        ],
        "sdk_ewm" => [
            "type" => 'text',
        ],
        "sdk_downloadinfo_url" => [
            "type" => 'text',
            'index' => false
        ],
        "sdk_gameinfo"=> [
            "type" => 'text',
        ],
        "quickdescription"=> [
            "type" => 'text',
        ],
        "sdk_size"=> [
            "type" => 'double',
        ],
        "remark"=> [
            "type" => 'text',
        ],
        "top_up_description"=> [
            "type" => 'text',
        ],
        "tags"=> [
            "type" => 'text',
        ],
        "seo"=> [
            "type" => 'text',
        ],
        "pic"=> [
            "type" => 'text',
            'index' => false
        ],
        "downloadurl"=> [
            "type" => 'text',
            'index' => false
        ],
        "encryptediosaddr"=> [
            "type" => 'text',
            'index' => false
        ],
        "encryptedandroidaddr"=> [
            "type" => 'text',
            'index' => false
        ],
        'for_sale_count' => [
            "type" => "integer"
        ],
        'sort' => [
            "type" => "integer"
        ],
        "name" => [
            "type" => "text",
            "analyzer" => "autocomplete",
            "search_analyzer" => "autocomplete",
            "fields" => [
                "pinyin" => [
                    "type" => "text",
                    "analyzer" => "pinyin_analyzer",
                    "search_analyzer" => "pinyin_analyzer",
                ],
                "standard" => [
                      "type" => "text",
                      "analyzer" => "standard",
                      "search_analyzer" => "standard"
                ],
            ]
        ],
    ];

    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function createIndex()
    {
        $elasticaIndex = $this->client->getIndex("idx_hcp_game");
        if ($elasticaIndex->exists()) {
            return;
        }

         $elasticaIndex->create(
             $this->index,
             false
         );

        $elasticaType = $elasticaIndex->getType("game");

        $mapping = new \Elastica\Type\Mapping();
        $mapping->setType($elasticaType);

        //Set mapping
        $mapping->setProperties($this->properties);
        // Send mapping to type
        $mapping->send();
    }

    /**
     * @return int 导入总数
     */
    public function import()
    {
         $elasticaType = $this->client
                     ->getIndex("idx_hcp_game")
                     ->getType('game')
                     ;

        $currentPage = 0;
        $games = $this->getGames($currentPage);

        $total = count($games);

        while (count($games) > 0) {
            $currentPage++;
            $documents = array();

            foreach ($games as $game) {
                $count = $this->culculateOnSaleCountByGame($game['id']);

                $document= new \Elastica\Document(
                    $game["id"],
                    array_merge(['for_sale_count' => $count], $game)
                );

                $document->setDocAsUpsert(true);

                $documents[] = $document;
            }
            $elasticaType->addDocuments($documents);

            //next 500
            $games = $this->getGames($currentPage);

            $total += count($games);
        }
        $elasticaType->getIndex()->refresh();

        return  $total;
    }

    private function getGames($currentPage, $pageSize = 500)
    {
        $offset = $currentPage * $pageSize;
        $sql = "SELECT * FROM g_games". ' limit '.$offset.','.$pageSize ;

        return Yii::$app->db->createCommand($sql)->queryAll();
    }

    private function culculateOnSaleCountByGame($gameId)
    {
        $sql = "select count(*)  from  t_trades where states= 2 and gameid= $gameId  and goodsid = 1 and isdel=0  and isshow=1 and CURRENT_DATE() <= enddate";

         return Yii::$app->db->createCommand($sql)->queryScalar();
    }
}
