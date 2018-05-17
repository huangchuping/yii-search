<?php

namespace tsy\search\controller;

use \Yii;
use yii\base\Controller;
use yii\base\Response;
use tsy\search\components\GameSearch;
use app\components\Tool;

class GameController extends Controller
{
    public function actionAutoComplete()
    {
        return $this->search(8);
    }

    public function actionIndex()
    {
        return $this->search(15);
    }

    private function search($defaultPageSize = 15)
    {
        $keyword = Yii::$app->request->get('keyword');
        if (empty($keyword)) {
            return   Yii::createObject([
            'class' => 'yii\web\Response',
            'data' => [
                'errCode' => 400,
                'errMessage' => '请输入游戏关键字',
            ],
            ]);
        }


        $page = Tool::GetSafeParam('page', 1, 0);
        $size = Tool::GetSafeParam('size', $defaultPageSize, 0);

        $client = Yii::$app->gameElastica->getClient();

        $gameSearch = new GameSearch($client);

        $result = $gameSearch->search($keyword, $page, $size);

        return  Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => array_merge(
                [
                'errCode' => 0,
                'errMessage' => 'ok',
                ],
                !empty($result)? $result: [
                'list' => [],
                'page' => [
                     'pagecount' =>  0,
                     'totalcount' =>0 ,
                    ]
                ]
            )
         ]);
    }
}
