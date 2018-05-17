<?php

namespace tsy\search\model;

class Game
{
    protected $elasticaType;

    public function __construct()
    {
        $client = \Yii::$app->gameElastica->getClient();
        $this->elasticaType = $client
             ->getIndex("idx_tsy_game")
             ->getType('game')
             ;
    }
    public function update($gameId, array $data)
    {
         $document  = new \Elastica\Document(
             $gameId,
             array_merge(
                 $data,
                 $this->normalizeDate($data)
             )
         );

        $this->elasticaType->updateDocument($document);
        $this->elasticaType->getIndex()->refresh();
    }

    public function create($id, $data)
    {
        $document  = new \Elastica\Document(
            $id,
            array_merge(
                ['for_sale_count' => 0],
                $data,
                $this->normalizeDate($data)
            )
        );

        $this->elasticaType->addDocument($document);
        $this->elasticaType->getIndex()->refresh();
    }

    public function remove($id)
    {
        $this->elasticaType->deleteById($id);
    }

    private function normalizeDate(array $data)
    {
        $nullable = [];

        foreach (['quickchargebegintime', 'quickchargeendtime', 'addtime'] as $key) {
            if (empty($model[$key])) {
                $nullable[$key] = null;
            }
        }

        return $nullable;
    }
}
