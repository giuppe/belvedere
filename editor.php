<?php
require_once "lib/Belvedere.php";
require_once "lib/HTTPTools.php";

$blv = new Belvedere();

$http = new HTTPTools();

if ($http->is_ajax()) {
    if (array_key_exists("action", $_GET)) {
        if ($_GET['action'] == "load") {
            if (! empty($_GET['file'])) {
                $result = $blv->getContentFileContent($_GET['file'], $_GET['dir']);
                if(array_key_exists('error', $result)){
                    header("HTTP/1.0 400 Bad Request");
                }
                echo json_encode($result);
            }
        } elseif ($_GET['action'] == 'save') {
            if (! empty($_GET['file'])) {
                $result =$blv->saveContentFileContent($_GET['file'], $_GET['dir'],$_POST['content']);
                if(array_key_exists('error', $result)){
                    header("HTTP/1.0 400 Bad Request");
                }
                echo json_encode($result);
            }
        }
    }
} else {
    ?>

<html>
<head>
<!-- Bootstrap core CSS -->
<link href="vendors/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="vendors/bootstrap-markdown/css/bootstrap-markdown.min.css"
	rel="stylesheet">

<script src="vendors/jquery/jquery-2.1.1.min.js"></script>
<!-- Bootstrap-Markdown JS -->
<script src="vendors/bootstrap-markdown/js/markdown.js"></script>
<script src="vendors/bootstrap-markdown/js/to-markdown.js"></script>
<script src="vendors/bootstrap-markdown/js/bootstrap-markdown.js"></script>

</head>
<body>
	<div id="filelist" class="col-md-3">
<?php

    function drawFileTree($filelist, $dirname = "")
    {
        ?>
  <ul>
  <?php
        foreach ($filelist as $dir => $filename) {
            if (! is_array($filename)) {
                ?>
    <li><a data-content="<?php echo $filename; ?>"
				data-contentdir="<?php echo $dirname; ?>"><?php echo $filename;?></a>

			</li><?php
            } else {
                ?>
    <li><a data-content="<?php echo $dir; ?>"
				data-contentdir="<?php echo $dirname; ?>"><?php echo $dir;?></a>
       <?php echo drawFileTree($filename, $dir);?>
    </li><?php
            }
        }
        ?>
</ul>
  <?php
    }
    ?>

<?php
    $filelist = $blv->getContentFilenames();
    echo drawFileTree($filelist);
    ?>
</div>
	<div id="editor" class="col-md-9">
		<h1 id="title"></h1>
		<form method="POST" action="editor.php">
			<input name="filename" type="hidden" /> <input name="dirname"
				type="hidden" /> <input name="saved" type="hidden" value="1" />
			<textarea name="content" id="markdown-editor" rows="10"></textarea>
			<hr />
			<div id="save-button">
				<button type="button" class="btn">Save</button>
			</div>
		</form>
	</div>
<?php
    
    ?>
<script>
$("<div>").addClass('metadata-editor').attr('id','metadata-editor').insertBefore($("#markdown-editor"));
    
    $("#markdown-editor").markdown({autofocus:false,savable:false});
$("#filelist li a").click(function(){
	var is_current_saved=$("#editor input[name=saved]").attr('value')==1;
	if(!is_current_saved){
		return;
	}
  var fileurl = "editor.php?action=load&file="+$(this).attr('data-content')+"&dir="+$(this).attr('data-contentdir');
	$.ajax({
		url: fileurl,
		success: function(dataresponse){
		    response = JSON.parse(dataresponse);
		    $("#markdown-editor").val(response.text);
		    $("#metadata-editor").html("");
		    $("#title").html(response.dir+"/"+response.name);
		    
		    $("#editor input[name=filename]").attr('value', response.name);
		    $("#editor input[name=dirname]").attr('value', response.dir);
		    
		    for (key in response.metadata) {
		        if (response.metadata.hasOwnProperty(key)) {
		        	 $("<label>").attr('for', key).html(key).appendTo($("#metadata-editor"));
		        	 $("<input>").attr('type', 'text').addClass("metadata-item").attr('name',key).attr('value',response.metadata[key]).appendTo($("#metadata-editor"));
		        	 
		        	  
			        }
		    }
		    
			}
		});
});

$("#editor").change(function(){
    $("#editor input[name=saved]").attr('value', '0');
	
});
$(".metadata-item").change(function(){
    $("#editor input[name=saved]").attr('value', '0');
	
});
$("<div>").attr('id', 'save-message').appendTo($("#save-button"));
$("#save-button button").click(function(){
  var filename=  $("#editor input[name=filename]").attr('value');
  var dirname =  $("#editor input[name=dirname]").attr('value');
  var content = "";
  $(".metadata-item").each(function(){
	    content+= $(this).attr('name')+" "+$(this).attr('value')+"\n";
	  });
  content += "\n"+$("#markdown-editor").val();
	var fileurl = "editor.php?action=save&file="+filename+"&dir="+dirname;
	$.ajax({
		url: fileurl,
		type: "POST",
		data: {"content": content},
		success: function(dataresponse){
		    response = JSON.parse(dataresponse);
		    $("#editor input[name=saved]").attr('value', '1');
		    $("#save-message").html("Last saved: "+response.save_date);
		}
	});
});
</script>
</body>
</html>
<?php
}
?>