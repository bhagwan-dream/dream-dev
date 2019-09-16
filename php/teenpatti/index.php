<html>
<head>
    <title>Live Teenpatti</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <style>
        .tenn-iframe, .tenn-iframe{
            height: 100vh;
            overflow: hidden !important;
        }
        .tenn-iframe, .tenn-iframe .inner-scroll {
            overflow: hidden !important;
        }
        .tenn-iframe iframe{
            /* height: inherit !important;
            overflow: hidden !important; */
        }
        .tenn-po{
            position: relative;
            height: 400px;
        }
        .screen-detail{
            overflow: scroll !important;
        }
        .i-suspended{
            position: absolute;
            top: 0;
            text-align: center;
            vertical-align: middle;
            align-items: center;
            display: grid;
            width: 100%;
            height: 100%;
            margin: 0 auto;
            background: rgba(0, 0, 0, 0.6784313725490196);
            color: #fff;
            text-transform: uppercase;
            font-size: 17px;
            letter-spacing: 2px;
            font-weight: 900;
            cursor: not-allowed;
            display: none;
        }

        .teen-title{
            text-align: left;
            background: #000;
            color: #fff;
            padding:8px 8px;
        }
        .teen-title span{
            font-weight: 600;
            font-size: 15px;
        }
        .teen-title span em{
            font-style: normal;
        }
        .teen-title span.expose-teen{
            box-shadow: inset 0px 0px 10px 2px #252525;
            padding: 5px;
            background: #0f2327;
            border-radius: 3px;
        }
        .mmm-teen{
            background: #f9f9f9;
        }
        .mmm-teen span{
            font-weight: 500;
            font-size: 11px;
            text-transform: capitalize;
        }
        .mmm-teen span em{
            font-style: normal;
            font-size: 9px;
            font-weight: 600;
        }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
</head>
<body>
<div class="row teen-title">
    <div class="col-6">

    </div>
    <div class="col-6 text-right">
        <div>
            <span class="expose-teen update-expose-data"> Expose: <em>0</em></span>
        </div>
    </div>
</div>
<div class="row mmm-teen text-center update-event-data">
    <div class="col-4">
        <span class="update-event-data-min-stake">Min Stake: <em>1000</em></span>
    </div>
    <div class="col-4 px-0">
        <span class="update-event-data-max-stake">Max Stake: <em>10000</em></span>
    </div>
    <div class="col-4 pl-0">
        <span class="update-event-data-max-profit">Max Profit: <em>100000</em></span>
    </div>
</div>
<?php if( isset( $_GET['token'] ) && $_GET['token'] != ''){ ?>
    <div class="tenn-iframe cf" id="div-iframe">
        <iframe class="w-100" src="http://m.fawk.app/#/splash-screen/<?php echo $_GET['token'];?>/9093" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="height: 100%;"></iframe>
    </div>
<?php }else{ ?>
    <div class="i-suspended" id="div-iframe">
        suspended
    </div>
<?php } ?>
<div class="i-suspended" id="div-suspend">
    suspended
</div>
</body>
<script>

    function close_window() {
        if (confirm("Close Window?")) {
            window.close();
        }
    }

    setInterval( getUserData, 6000);
    function getUserData() {
        var token = '<?php echo $_GET['token'];?>';
        var request = $.ajax({
            url: "http://api.dreamexch9.com/api/poker/app-user-auth",
            type: "POST",
            data: {token : token },
            dataType: "json"
        });

        request.done(function(res) {
            if( res.status == 1 ){
                $('#div-suspend').hide();

                var exposeData = 'Expose: <em>'+res.data.expose+'</em>';
                $('.update-expose-data').html(exposeData);

                if( res.data.eventData != null ){
                    $('.update-event-data-min-stake').html('Min Stake: <em>'+res.data.eventData.min_stack+'</em>');
                    $('.update-event-data-max-stake').html('Max Stake: <em>'+res.data.eventData.max_stack+'</em>');
                    $('.update-event-data-max-profit').html('Max Profit: <em>'+res.data.eventData.max_profit_all_limit+'</em>');
                }

            }else{
                $('#div-suspend').show();
                $('#div-iframe').hide();
            }
        });
    }


</script>
</html>

