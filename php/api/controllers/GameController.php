<?php
namespace api\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use common\models\GameMatkaSchedule;


/**
 * Game controller
 */
class GameController extends Controller
{
    public $enableCsrfValidation = false;
    
    private $apiUserToken = '13044-CgPWGpYSAOn7aV';
    
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    // Test Timer
    public function actionAddSchedule()
    {
        
        // $date1 and $date2 are given
        // the difference is in seconds
        //$date2 = date("2018-12-25 11:08:00");
        //echo date("Y-m-d H:i:s");exit;
        //$date1 = date("Y-m-d H:i:s");
        
        //$difference = strtotime($date2)-strtotime($date1);
        
        // getting the difference in minutes
        //$difference_in_minutes = $difference / 60*60;
        //echo $difference_in_minutes;exit;
        
        $start = date("Y-m-d H:i:s");
        $result_end = null;
        $gameArr = [];
        
        for ($i = 1; $i < 500; $i++) {
            
            if( $result_end == null && $i==1 && $start != null ){
                $result_start = date("Y-m-d H:i:s",strtotime($start." +60 seconds"));
                $result_end = date("Y-m-d H:i:s",strtotime($result_start." +20 seconds"));
            }else{
                $start = $result_end;
                $result_start = date("Y-m-d H:i:s",strtotime($start." +60 seconds"));
                $result_end = date("Y-m-d H:i:s",strtotime($result_start." +20 seconds"));
            }
            
            $gameArr[] = [
                'start_game' => strtotime($start),
                'end_game' => strtotime($result_start),
                'result_start' => strtotime($result_start),
                'result_end' => strtotime($result_end),
                'is_game_completed' => 0,
                'is_result_completed' => 0,
                'created_at' => time(),
                'updated_at' => time(),
                'status' => 1,
            ];
        
        }
        
        //echo $result_end;
        //echo '<pre>'; print_r($gameArr);die;
        
        $command = \Yii::$app->db->createCommand();
        $attrArr = ['start_game','end_game','result_start','result_end','is_game_completed','is_result_completed','created_at','updated_at','status'];
        $qry = $command->batchInsert('game_matka_schedule', $attrArr, $gameArr);
        if( $qry->execute() ){
            echo 'Add Schedule Successfully!';exit;
        }else{
            echo 'Error in Add Schedule!!';exit;
        }
        
    }
    
    //Game Time
    public function actionRunningGame()
    {
        
        //echo $now = strtotime(date("Y-m-d H:i:s")).' - 1545736500';die;
        //$now = strtotime(date("Y-m-d H:i:s"));
        //$condition = 'start_game > '.$now.' AND end_game < '.$now;
        $model = GameMatkaSchedule::find()
        ->where(['<','start_game',strtotime(date("Y-m-d H:i:s"))])
        ->andWhere(['>','end_game',strtotime(date("Y-m-d H:i:s"))])
        ->one();
        if( $model != null ){
            
            $diff = $model->end_game-strtotime(date("Y-m-d H:i:s"));
            
            $timer = $diff / 60*60;
            echo 'Game Start In: '.$timer.' sec';
            echo '<pre>';print_r($model);die;
            
        }else{
            
            //$now = strtotime(date("Y-m-d H:i:s"));
            $model = GameMatkaSchedule::find()
            ->where(['<','result_start',strtotime(date("Y-m-d H:i:s"))])
            ->andWhere(['>','result_end',strtotime(date("Y-m-d H:i:s"))])
            ->one();
            if( $model != null ){
                
                $diff = $model->result_end-strtotime(date("Y-m-d H:i:s"));
                $timer = $diff / 60*60;
                echo 'Game Result In: '.$timer.' sec';
                echo '<pre>';print_r($model);die;
                
            }else{
                echo 'There is no any Game!!';die;
            }
            
        }
        
        
    }
    
    // Game Over Update
    public function actionUpdateGameOver()
    {
        
        $model = GameMatkaSchedule::find()
        ->where(['<','start_game',strtotime(date("Y-m-d H:i:s"))])
        ->andWhere(['>','result_end',strtotime(date("Y-m-d H:i:s"))])
        ->one();
        
        if( $model != null && GameMatkaSchedule::updateAll(['is_game_completed'=>1,'is_result_completed'=>1],'id < '.$model->id ) ){
            echo 'All Game Updated!!';die;
        }else{
            echo 'There is no any Game!!';die;
        }
    }
}
