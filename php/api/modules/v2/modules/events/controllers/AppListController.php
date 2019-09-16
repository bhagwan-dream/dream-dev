<?php
namespace api\modules\v2\modules\events\controllers;

class AppListController extends \common\controllers\aController  // \yii\rest\Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $behaviors [ 'access' ] = [
            'class' => \yii\filters\AccessControl::className(),
            'rules' => [
                [
                    'allow' => true, 'roles' => [ 'client' ],
                ],
            ],
            "denyCallback" => [ \common\controllers\cController::className() , 'accessControlCallBack' ]
        ];
        
        return $behaviors;
    }
    
    //Event: Index
    public function actionIndex()
    {

        $uid = \Yii::$app->user->id;

        $eventData = (new \yii\db\Query())
        ->select(['sport_id','market_id','event_id','event_name','event_league','event_time','play_type','suspended','ball_running'])
        ->from('events_play_list')
        ->where(['game_over'=>'NO','play_type' => ['IN_PLAY','UPCOMING','CLOSED'],'status'=>1])
        ->andWhere(['>','event_time',strtotime( date('Y-m-d').' 23:59:59 -7 day') * 1000])
        ->orderBy(['event_time' => SORT_ASC])
        ->createCommand(\Yii::$app->db3)->queryAll();
        
        $responseData = [];
        if( $eventData != null ){

            $unblockEvents = $this->checkUnBlockList($uid,$eventData);
            $unblockSport = $this->checkUnBlockSportList($uid);

            foreach ( $eventData as $event ){
                
                $eventId = $event['event_id'];
                $marketId = $event['market_id'];
                $sportId = $event['sport_id'];
                
                $isFavorite = $this->isFavorite($uid,$eventId,$marketId,'match_odd');

                $blocklistArr = (new \yii\db\Query())
                    ->select(['event_id'])->from('event_market_status')
                    ->where(['event_id'=>$eventId,'market_type' => 'all' , 'byuser' =>1 ])
                    ->createCommand(\Yii::$app->db1)->queryOne();

                if( (!in_array($eventId,  $unblockEvents )) && (empty( $blocklistArr) )) {
                    
                    if( $sportId == '4' ){
                        
                        $responseData['cricket'][] = [
                            'slug' => 'cricket',
                            'type' => $event['play_type'],
                            'sportId' => $sportId,
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ballRunning' => $event['ball_running'],
                        ];
                        
                         $eventData = (new \yii\db\Query())
                            ->select(['event_id'])
                            ->from('cricket_jackpot')
                            ->where(['event_id' => $event['event_id'],'game_over'=>'NO','status' => 1])
                            ->createCommand(\Yii::$app->db1)->queryOne();

                        if( $eventData != null && !empty($eventData) ){
                            $responseData['jackpot'][] = [
                                'slug' => 'jackpot',
                                'type' => $event['play_type'],
                                'sportId' => $sportId,
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ballRunning' => $event['ball_running'],
                            ];
                        }

                    }else if( $sportId == '2' ){
                        
                        $responseData['tennis'][] = [
                            'slug' => 'tennis',
                            'type' => $event['play_type'],
                            'sportId' => $sportId,
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ballRunning' => $event['ball_running'],
                        ];
                        
                    }else if( $sportId == '1' ){
                        
                        $responseData['football'][] = [
                            'slug' => 'football',
                            'type' => $event['play_type'],
                            'sportId' => $sportId,
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ballRunning' => $event['ball_running'],
                        ];
                        
                    }
                }
                
                if( in_array(1, $unblockSport ) ){
                    $responseData['football'] = [];
                    $responseData['isFootballBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                }
                if( in_array(2, $unblockSport ) ){
                    $responseData['tennis'] = [];
                    $responseData['isTennisBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                }
                if( in_array(4, $unblockSport ) ){
                    $responseData['cricket'] = [];
                    $responseData['isCricketBlock'] = ['status' => 1 , 'msg' => 'This Sport Block by Parent!'];
                }
                
            }
            
        }
        
        return [ "status" => 1 , "data" => [ "items" => $responseData ] ];
        
    }
    
    //Event - Inplay Today Tomorrow
    public function actionInplayTodayTomorrow()
    {
        $dataArr = $cricketInplay = $tennisInplay = $footballInplay = $horseracingInplay = [];
        $cricketToday = $tennisToday = $footballToday = $horseracingToday = [];
        $cricketTomorrow = $tennisTomorrow = $footballTomorrow = $horseracingTomorrow = [];

        $uid = \Yii::$app->user->id;

        $eventData = (new \yii\db\Query())
        ->select(['sport_id','market_id','event_id','event_name','event_league','event_time','play_type','suspended','ball_running'])
        ->from('events_play_list')
        ->where(['game_over'=>'NO','status'=>1])
        ->andWhere(['>','event_time',strtotime( date('Y-m-d').' 23:59:59 -7 day') * 1000])
        ->andWhere(['<','event_time',strtotime( date('Y-m-d').' 23:59:59 +1 day') * 1000])
        ->orderBy(['event_time' => SORT_ASC])
        ->createCommand(\Yii::$app->db3)->queryAll();
        
        if( $eventData != null ){

            $unblockEvents = $this->checkUnBlockList($uid,$eventData);
            $unblockSport = $this->checkUnBlockSportList($uid);

            foreach ( $eventData as $event ){
                
                $eventId = $event['event_id'];
                $marketId = $event['market_id'];
                $isFavorite = $this->isFavorite($uid,$eventId,$marketId,'match_odd');
                
                $today = date('Y-m-d');
                $tomorrow = date('Y-m-d' , strtotime($today . ' +1 day') );
                $eventDate = date('Y-m-d',( $event['event_time'] / 1000 ));
                
                //In play List
                if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 4
                    ){
                        
                        if( !in_array($eventId, $unblockEvents ) ){
                            
                            $cricketInplay[] = [
                                'slug' => 'cricket',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                        
                }else if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 2
                    ){
                        
                        if( !in_array($eventId, $unblockEvents ) ){
                            
                            $tennisInplay[] = [
                                'slug' => 'tennis',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                        
                }else if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 1
                    ){
                        
                        if( !in_array($eventId, $unblockEvents ) ){
                            
                            $footballInplay[] = [
                                'slug' => 'football',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                }else{
                    // Do nothing
                }
                
                //Upcoming List Today
                if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 4
                    && $today == $eventDate ){
                        
                        if( !in_array($eventId, $unblockEvents ) ){
                            
                            $cricketToday[] = [
                                'slug' => 'cricket',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                        
                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 2
                    && $today == $eventDate ){
                        
                        if( !in_array($eventId, $unblockEvents ) ){
                            
                            $tennisToday[] = [
                                'slug' => 'tennis',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                        
                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 1
                    && $today == $eventDate ){
                        if( !in_array($eventId, $unblockEvents ) ){
                            $footballToday[] = [
                                'slug' => 'football',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                }else{
                    // Do nothing
                }
                
                //Upcoming List Tomorrow
                if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 4
                    && $tomorrow == $eventDate ){
                        if( !in_array($eventId, $unblockEvents ) ){
                            $cricketTomorrow[] = [
                                'slug' => 'cricket',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                        
                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id']== 2
                    && $tomorrow == $eventDate ){
                        if( !in_array($eventId, $unblockEvents ) ){
                            $tennisTomorrow[] = [
                                'slug' => 'tennis',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                        
                }else if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 1
                    && $tomorrow == $eventDate ){
                        if( !in_array($eventId, $unblockEvents ) ){
                            $footballTomorrow[] = [
                                'slug' => 'football',
                                'eventId' => $eventId,
                                'marketId' => $marketId,
                                'title' => $event['event_name'],
                                'league' => $event['event_league'],
                                'time' => $event['event_time'],
                                'is_favorite' => $isFavorite,
                                'suspended' => $event['suspended'],
                                'ball_running' => $event['ball_running'],
                            ];
                        }
                }else{
                    // Do nothing
                }
                
                
            }
            
        }
        
        if( in_array(1, $unblockSport ) ){
            $footballInplay = $footballToday = $footballTomorrow = [];
        }
        if( in_array(2, $unblockSport ) ){
            $tennisInplay = $tennisToday = $tennisTomorrow = [];
        }
        if( in_array(4, $unblockSport ) ){
            $cricketInplay = $cricketToday = $cricketTomorrow = [];
        }
        
        $dataArr['inplay'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketInplay
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisInplay
            ],
            [
                'title' => 'Football',
                'list' => $footballInplay
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingInplay
            ]
        ];
        $dataArr['today'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketToday
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisToday
            ],
            [
                'title' => 'Football',
                'list' => $footballToday
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingToday
            ]
        ];
        $dataArr['tomorrow'] = [
            [
                'title' => 'Cricket',
                'list' => $cricketTomorrow
            ],
            [
                'title' => 'Tennis',
                'list' => $tennisTomorrow
            ],
            [
                'title' => 'Football',
                'list' => $footballTomorrow
            ],
            [
                'title' => 'Horse Racing',
                'list' => $horseracingTomorrow
            ]
        ];
        return [ "status" => 1 , "data" => [ "items" => $dataArr ] ];
    }
    
    //Event: isFavorite
    public function isFavorite($uid,$eventId,$marketId,$sessionType)
    {
        //$uid = \Yii::$app->user->id;
        
        $find = (new \yii\db\Query())
        ->select(['id'])->from('favorite_market')
        ->where(['user_id'=>$uid,'event_id' => $eventId,'market_id' => $marketId,'market_type' => $sessionType ])
        ->createCommand(\Yii::$app->db1)->queryOne();
        
        if( $find != null ){
            return '1';
        }
        return '0';
        
    }

    //check database function
    public function checkUnBlockList($uId,$eventData)
    {
        $eventArr = [];
        if( $eventData != null ){

            foreach ( $eventData as $event ){

                $eventArr[] = $event['event_id'];

            }
            //echo '<pre>';print_r($eventArr);die;
            //$uId = \Yii::$app->user->id;

            $user = (new \yii\db\Query())
                ->select(['parent_id'])
                ->from('user')
                ->where(['id'=> $uId])
                ->createCommand(\Yii::$app->db3)->queryOne();

            //$user = User::find()->select( ['parent_id'] )->where(['id'=>$uId])->one();

            $pId = 1;
            if( $user != null ){
                $pId = $user['parent_id'];
            }
            $newList = [];
            $listArr = (new \yii\db\Query())
                ->select(['event_id'])->from('event_market_status')
                ->where(['user_id'=>$pId,'market_type' => 'all' ])
                ->andWhere(['IN','event_id',$eventArr])
                ->createCommand(\Yii::$app->db3)->queryAll();

            if( $listArr != null ){

                foreach ( $listArr as $list ){
                    $newList[] = $list['event_id'];
                }

                return $newList;
            }else{
                return [];
            }

        }else{
            return [];
        }
    }
    
    //check sport database function
    public function checkUnBlockSportList($uId)
    {
        //$uId = \Yii::$app->user->id;

        $user = (new \yii\db\Query())
            ->select(['parent_id'])
            ->from('user')->where(['id'=> $uId])
            ->createCommand(\Yii::$app->db3)->queryOne();

//        $user = User::find()->select( ['parent_id'] )
//        ->where(['id'=>$uId])->one();

        $pId = 1;
        if( $user != null ){
            $pId = $user['parent_id'];
        }
        $newList = [];
        $listArr = (new \yii\db\Query())
        ->select(['sport_id'])->from('event_status')
        ->where(['user_id'=>$pId ])->createCommand(\Yii::$app->db3)->queryAll();
        
        if( $listArr != null ){
            
            foreach ( $listArr as $list ){
                $newList[] = $list['sport_id'];
            }
            
            return $newList;
        }else{
            return [];
        }
        
    }
    
    
}
