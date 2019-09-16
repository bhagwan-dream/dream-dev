<?php
namespace api\modules\v2\modules\events\controllers;

use common\models\User;

class ListController extends \common\controllers\aController  // \yii\rest\Controller
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
        ->where(['game_over'=>'NO','status'=>1 , 'play_type' => ['IN_PLAY','UPCOMING','CLOSED'] ])
        ->andWhere(['>','event_time',strtotime( date('Y-m-d').' 23:59:59 -7 day') * 1000])
        ->orderBy(['event_time' => SORT_ASC])
        ->createCommand(\Yii::$app->db3)->queryAll();
        
        $responseData = [];
        if( $eventData != null ){
            
            foreach ( $eventData as $event ){
                
                $eventId = $event['event_id'];
                $marketId = $event['market_id'];
                $sportId = $event['sport_id'];
                
                $isFavorite = $this->isFavorite($uid,$eventId,$marketId,'match_odd');
                $runnersArr = [];
                if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
                    
                    if( $sportId == '4' ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                        ->createCommand(\Yii::$app->db3)->queryAll();
                        
                        if( $runnerData != null ){
                            
                            $cache = \Yii::$app->cache;
                            $oddsData = $cache->get($marketId);
                            //echo '<pre>';print_r($oddsData);

                            if( $oddsData != false ){
                                $oddsData = json_decode($oddsData);
                                if( $oddsData->odds != null ) {
                                    $i = 0;
                                    foreach ($oddsData->odds as $odds) {

                                        $back[$i] = [
                                            'price' => $odds->backPrice1,
                                            'size' => $odds->backSize1,
                                        ];
                                        $lay[$i] = [
                                            'price' => $odds->layPrice1,
                                            'size' => $odds->laySize1,
                                        ];
                                        $i++;
                                    }
                                }
                            }else{

                                $back[0] = $back[1] = $back[2] = $lay[0] = $lay[1] = $lay[2] = [
                                    'price' => '-',
                                    'size' => ''
                                ];

                            }
                            $i=0;
                            foreach ( $runnerData as $runners ){
                                
                                if( !isset( $back[$i] ) ){
                                    $back[$i] = [
                                        'price' => '-',
                                        'size' => ''
                                    ];
                                }
                                if( !isset( $lay[$i] ) ){
                                    $lay[$i] = [
                                        'price' => '-',
                                        'size' => ''
                                    ];
                                }
                                
                                $selectionId = $runners['selection_id'];
                                $runnerName = $runners['runner'];
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'exchange' => [
                                        'back' => $back[$i],
                                        'lay' => $lay[$i],
                                    ]
                                ];
                                $i++;
                            }
                            
                        }
                        
                        $responseData['cricket'][] = [
                            'slug' => 'cricket',
                            'type' => $event['play_type'] == 'CLOSED' ? 'UPCOMING' : $event['play_type'],
                            'sportId' => $sportId,
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ballRunning' => $event['ball_running'],
                            'runners' => $runnersArr
                        ];
                        

                         $eventData = (new \yii\db\Query())
                        ->select(['event_id'])
                        ->from('cricket_jackpot')
                        ->where(['event_id' => $event['event_id'],'status' => 1 , 'game_over'=>'NO' ])
                        ->createCommand(\Yii::$app->db1)->queryOne();

                        if( $eventData != null && !empty($eventData) ){
                         $responseData['jackpot'][] = [
                            'slug' => 'jackpot',
                            'type' => $event['play_type'] == 'CLOSED' ? 'UPCOMING' : $event['play_type'],
                            'sportId' => $sportId,
                            'eventId' => $eventId,
                            'marketId' => $marketId,
                            'title' => $event['event_name'],
                            'league' => $event['event_league'],
                            'time' => $event['event_time'],
                            'is_favorite' => $isFavorite,
                            'suspended' => $event['suspended'],
                            'ballRunning' => $event['ball_running'],
                            'runners' => $runnersArr
                        ];
                    }

                    }else if( $sportId == '2' ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                        ->createCommand(\Yii::$app->db3)->queryAll();
                        
                        if( $runnerData != null ){
                            
                            $cache = \Yii::$app->cache;
                            $oddsData = $cache->get($marketId);
                            $oddsData = json_decode($oddsData);
                            //echo '<pre>';print_r($oddsData);die;
                            if( $oddsData != null && $oddsData->odds != null ){
                                $i=0;
                                foreach ( $oddsData->odds as $odds ){
                                    
                                    $back[$i] = [
                                        'price' => $odds->backPrice1,
                                        'size' => $odds->backSize1,
                                    ];
                                    $lay[$i] = [
                                        'price' => $odds->layPrice1,
                                        'size' => $odds->laySize1,
                                    ];
                                    $i++;
                                }
                            }
                            $i=0;
                            foreach ( $runnerData as $runners ){
                                
                                if( !isset( $back[$i] ) ){
                                    $back[$i] = [
                                        'price' => '-',
                                        'size' => ''
                                    ];
                                }
                                if( !isset( $lay[$i] ) ){
                                    $lay[$i] = [
                                        'price' => '-',
                                        'size' => ''
                                    ];
                                }
                                
                                $selectionId = $runners['selection_id'];
                                $runnerName = $runners['runner'];
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'exchange' => [
                                        'back' => $back[$i],
                                        'lay' => $lay[$i],
                                    ]
                                ];
                                $i++;
                            }
                            
                        }
                        
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
                            'runners' => $runnersArr
                        ];
                        
                    }else if( $sportId == '1' ){
                        
                        $runnerData = (new \yii\db\Query())
                        ->select(['selection_id','runner'])
                        ->from('events_runners')
                        ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                        ->createCommand(\Yii::$app->db3)->queryAll();
                        
                        if( $runnerData != null ){
                            
                            $cache = \Yii::$app->cache;
                            $oddsData = $cache->get($marketId);
                            $oddsData = json_decode($oddsData);
                            //echo '<pre>';print_r($oddsData);die;
                            if( $oddsData != null && $oddsData->odds != null ){
                                $i=0;
                                foreach ( $oddsData->odds as $odds ){
                                    
                                    $back[$i] = [
                                        'price' => $odds->backPrice1,
                                        'size' => $odds->backSize1,
                                    ];
                                    $lay[$i] = [
                                        'price' => $odds->layPrice1,
                                        'size' => $odds->laySize1,
                                    ];
                                    $i++;
                                }
                            }
                            $i=0;
                            foreach ( $runnerData as $runners ){
                                
                                if( !isset( $back[$i] ) ){
                                    $back[$i] = [
                                        'price' => '-',
                                        'size' => ''
                                    ];
                                }
                                if( !isset( $lay[$i] ) ){
                                    $lay[$i] = [
                                        'price' => '-',
                                        'size' => ''
                                    ];
                                }
                                
                                $selectionId = $runners['selection_id'];
                                $runnerName = $runners['runner'];
                                
                                $runnersArr[] = [
                                    'selectionId' => $selectionId,
                                    'runnerName' => $runnerName,
                                    'exchange' => [
                                        'back' => $back[$i],
                                        'lay' => $lay[$i],
                                    ]
                                ];
                                $i++;
                            }
                            
                        }
                        
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
                            'runners' => $runnersArr
                        ];
                        
                    }
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
                        
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
                            $runnersArr = [];
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                            ->createCommand(\Yii::$app->db3)->queryAll();
                            
                            if( $runnerData != null ){
                                
                                $cache = \Yii::$app->cache;
                                $oddsData = $cache->get($marketId);
                                $oddsData = json_decode($oddsData);
                                //echo '<pre>';print_r($oddsData);die;
                                if( $oddsData != null && $oddsData->odds != null ){
                                    $i=0;
                                    foreach ( $oddsData->odds as $odds ){
                                        
                                        $back[$i] = [
                                            'price' => $odds->backPrice1,
                                            'size' => $odds->backSize1,
                                        ];
                                        $lay[$i] = [
                                            'price' => $odds->layPrice1,
                                            'size' => $odds->laySize1,
                                        ];
                                        $i++;
                                    }
                                }
                                $i=0;
                                foreach ( $runnerData as $runners ){
                                    
                                    if( !isset( $back[$i] ) ){
                                        $back[$i] = [
                                            'price' => '-',
                                            'size' => ''
                                        ];
                                    }
                                    if( !isset( $lay[$i] ) ){
                                        $lay[$i] = [
                                            'price' => '-',
                                            'size' => ''
                                        ];
                                    }
                                    
                                    $selectionId = $runners['selection_id'];
                                    $runnerName = $runners['runner'];
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'exchange' => [
                                            'back' => $back[$i],
                                            'lay' => $lay[$i],
                                        ]
                                    ];
                                    $i++;
                                }
                                
                            }
                            
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
                                'runners' => $runnersArr
                            ];
                        }
                        
                }else if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 2
                    ){
                        
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
                            $runnersArr = [];
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                            ->createCommand(\Yii::$app->db3)->queryAll();
                            
                            if( $runnerData != null ){
                                
                                $cache = \Yii::$app->cache;
                                $oddsData = $cache->get($marketId);
                                $oddsData = json_decode($oddsData);
                                //echo '<pre>';print_r($oddsData);die;
                                if( $oddsData != null && $oddsData->odds != null ){
                                    $i=0;
                                    foreach ( $oddsData->odds as $odds ){
                                        
                                        $back[$i] = [
                                            'price' => $odds->backPrice1,
                                            'size' => $odds->backSize1,
                                        ];
                                        $lay[$i] = [
                                            'price' => $odds->layPrice1,
                                            'size' => $odds->laySize1,
                                        ];
                                        $i++;
                                    }
                                }
                                $i=0;
                                foreach ( $runnerData as $runners ){
                                    
                                    if( !isset( $back[$i] ) ){
                                        $back[$i] = [
                                            'price' => '-',
                                            'size' => ''
                                        ];
                                    }
                                    if( !isset( $lay[$i] ) ){
                                        $lay[$i] = [
                                            'price' => '-',
                                            'size' => ''
                                        ];
                                    }
                                    
                                    $selectionId = $runners['selection_id'];
                                    $runnerName = $runners['runner'];
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'exchange' => [
                                            'back' => $back[$i],
                                            'lay' => $lay[$i],
                                        ]
                                    ];
                                    $i++;
                                }
                                
                            }
                            
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
                                'runners' => $runnersArr
                            ];
                        }
                        
                }else if( $event['play_type'] == 'IN_PLAY'
                    && $event['sport_id'] == 1
                    ){
                        
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
                            $runnersArr = [];
                            $runnerData = (new \yii\db\Query())
                            ->select(['selection_id','runner'])
                            ->from('events_runners')
                            ->where(['event_id' => $eventId , 'market_id' => $marketId ])
                            ->createCommand(\Yii::$app->db3)->queryAll();
                            
                            if( $runnerData != null ){
                                
                                $cache = \Yii::$app->cache;
                                $oddsData = $cache->get($marketId);
                                $oddsData = json_decode($oddsData);
                                //echo '<pre>';print_r($oddsData);die;
                                if( $oddsData != null && $oddsData->odds != null ){
                                    $i=0;
                                    foreach ( $oddsData->odds as $odds ){
                                        
                                        $back[$i] = [
                                            'price' => $odds->backPrice1,
                                            'size' => $odds->backSize1,
                                        ];
                                        $lay[$i] = [
                                            'price' => $odds->layPrice1,
                                            'size' => $odds->laySize1,
                                        ];
                                        $i++;
                                    }
                                }
                                $i=0;
                                foreach ( $runnerData as $runners ){
                                    
                                    if( !isset( $back[$i] ) ){
                                        $back[$i] = [
                                            'price' => '-',
                                            'size' => ''
                                        ];
                                    }
                                    if( !isset( $lay[$i] ) ){
                                        $lay[$i] = [
                                            'price' => '-',
                                            'size' => ''
                                        ];
                                    }
                                    
                                    $selectionId = $runners['selection_id'];
                                    $runnerName = $runners['runner'];
                                    
                                    $runnersArr[] = [
                                        'selectionId' => $selectionId,
                                        'runnerName' => $runnerName,
                                        'exchange' => [
                                            'back' => $back[$i],
                                            'lay' => $lay[$i],
                                        ]
                                    ];
                                    $i++;
                                }
                                
                            }
                            
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
                                'runners' => $runnersArr
                            ];
                        }
                }else{
                    // Do nothing
                }
                
                //Upcoming List Today
                if( $event['play_type'] == 'UPCOMING'
                    && $event['sport_id'] == 4
                    && $today == $eventDate ){
                        
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
                            
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
                        
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
                            
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
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
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
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
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
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
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
                        if( !in_array($eventId, $this->checkUnBlockList($uid) ) ){
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
        ->createCommand(\Yii::$app->db3)->queryOne();
        
        if( $find != null ){
            return '1';
        }
        return '0';
        
    }
    
    //check database function
    public function checkUnBlockList($uId)
    {
        //$uId = \Yii::$app->user->id;
        $user = User::find()->select( ['parent_id'] )
        ->where(['id'=>$uId])->createCommand(\Yii::$app->db3)->queryOne();
        $pId = 1;
        if( $user != null ){
            $pId = $user['parent_id'];
        }
        $newList = [];
        $listArr = (new \yii\db\Query())
        ->select(['event_id'])->from('event_market_status')
        ->where(['user_id'=>$pId,'market_type' => 'all' ])->createCommand(\Yii::$app->db3)->queryAll();
        
        if( $listArr != null ){
            
            foreach ( $listArr as $list ){
                $newList[] = $list['event_id'];
            }
            
            return $newList;
        }else{
            return [];
        }
        
    }
    
    
}
