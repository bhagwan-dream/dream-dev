<html>
<head>
</head>
<body>
<form id="myform" action="<?php echo $baseurl; ?>/site/manualsession" method="post">
	<input type="text" onBlur="onChangeYesNoVal(this.value,'0', '');" class="input" id="run" name="Form[no_yes_val_2]" >
	<input type="text" onBlur="onChangeYesNoRate(this.value,'1', '');" class="input" id="rate" name="Form[rate_2]" > 
	<input type="submit" value="submit"> 
</form>

</body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script>	

function submitFormData(){

	var form = $('form#myform');
    $.ajax({
          url    : form.attr('action'),
          type   : 'POST',
          data   : form.serialize(),
          success: function (response) 
          {                  
             alert(response);
             form[0].reset();
          },
          error  : function () 
          {
              console.log('internal server error');
              form[0].reset();
          }
      });
}


 function onChangeYesNoVal(event,val,item){
    /*if(!Number($event)){
        return false;
    }*/
 
    var tmp = '';
    if(event.length >= 2){
        var newStr = event.split('/');
        if( val == '1'){
            tmp  = (newStr[0])+'/'+ (Number(newStr[0]) + Number(val));
        }

        if( val == '0'){
            tmp  = newStr[0]+'/'+ newStr[0];
        }
        
        $('#run').val(tmp);
    }
}

function onChangeYesNoRate(event,val,item){
     /*if(!Number(event)){
         return false;
     }*/
     var tmp = '';
     var match = {70:'70/90',60:'60/90',75:'75/95',76:'75/125',80:'80/120',81:'80/130',85:'85/115',90:'90/110',
     105:'105/130',110:'110/150',120:'120/170',130:'130/200',150:'150/250',250:'250/500'};
     
     if(event.length >= 2){
         var newStr = event.split('/');
         if( val == '1'){
             tmp  = match[newStr[0]];
             if(tmp === undefined){
                 tmp = newStr[0] +'/'+ (Number(newStr[0]) + Number(10)); 
             }
             //item.rate_2 = tmp;
			//$('#rate').val(tmp);	
             //this.save(item);
         }

         if( val == '0'){
             tmp = newStr[0] +'/'+ newStr[0]; 
         }
     	
     	$('#rate').val(tmp); //set value

     	submitFormData(); //submit form
     	
     }else{
         //this.save(item);
         alert(item);
     }
 }


</script>
</html>
