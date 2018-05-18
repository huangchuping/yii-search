<?php

namespace hcp\search\console;

use yii\console\Controller;
use yii\helpers\Console;
use Yii;
use hcp\search\components\GameExporter;
use hcp\search\components\GameIndex;

class GameController extends Controller
{
    /**
     * 创建Game Mappings, 并从MySQL导入数据到ES
     */
    public function actionIndex()
    {
        $client = Yii::$app->gameElastica->getClient();
        $gameIndex = new GameIndex($client);

        $this->stdout(date('Y-m-d H:i:s', time())."\n创建Game Index\n", Console::FG_GREEN);
        $gameIndex->createIndex();
        $this->stdout("Game Index创建成功\n", Console::FG_GREEN);

        $this->stdout("从MySQL导入游戏数据到ES\n", Console::FG_GREEN);
        $total = $gameIndex->import();
        $this->stdout(sprintf("数据导入完成，共导入%s条数据\n", $total), Console::FG_GREEN);
    }

    /**
     * 导出全部游戏数据为json文件
     */
    public function actionExportJson()
    {
        $this->stdout(date('Y-m-d H:i:s', time())."\n获取全部游戏\n");
        $client = Yii::$app->gameElastica->getClient();

        $gameExporter = new GameExporter($client);

        $dir = Yii::getAlias('@app').'/web/static/common/static/json/';
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->stdout("导出路径:\n");
        $this->stdout($dir."\n");

        $games = $gameExporter->export();

        file_put_contents($dir.'gamelist.json.html', json_encode($games));

        $this->stdout("导出全部游戏成功\n", Console::FG_GREEN);

        $this->stdout("获取游戏商品关系JSON\n");
        $client = new \GuzzleHttp\Client();
        $response = $client->request(
            'GET',
            Yii::$app->params['cmd_domainname'].'/com/game2good/getgame-trades-list?_='. date('Y-m-d H:i:s', time())
        );

        file_put_contents($dir.'game2goodlist.json.html', (string)$response->getBody());
        $this->stdout("导出游戏商品关系JSON\n", Console::FG_GREEN);
    }

    /**
     * 将没有任何商品的游戏设置为不显示。
     */
    public function actionGamesIsshow()
    {
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            // 更新续充为不显示状态
            $sql3 = "UPDATE g_games SET isshow = 0 
            WHERE id  IN(SELECT gameid FROM t_trades WHERE goodsid = 9 AND states = 2 GROUP BY gameid ORDER BY id DESC) AND isshow = 1";
            $command = Yii::$app->db->createCommand($sql3);
            $command->execute();

            // 更新游戏为显示状态
            $sql = "UPDATE g_games SET isshow = 1 
            WHERE id IN(SELECT gameid FROM t_trades WHERE states = 2 AND goodsid <> 9 ORDER BY id DESC) AND isshow = 0";
            $command = Yii::$app->db->createCommand($sql);
            $command->execute();
 
            // 更新游戏为不显示状态
            $sql2 = "UPDATE g_games SET isshow = 0 
            WHERE id  NOT IN(SELECT gameid FROM t_trades WHERE states = 2 ORDER BY id DESC) AND isshow = 1";
            $command = Yii::$app->db->createCommand($sql2);
            $command->execute();

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            var_dump($e);
            exit();
        }
    }
}
