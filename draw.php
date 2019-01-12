<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Canvas Area Draw</title>
    <link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/css/bootstrap.no-icons.min.css" rel="stylesheet">
    <link href="//netdna.bootstrapcdn.com/font-awesome/3.0/css/font-awesome.css" rel="stylesheet">
    <link href="//netdna.bootstrapcdn.com/font-awesome/3.0/css/font-awesome-ie7.css" rel="stylesheet">
  </head>
  <body>
    <div id="main" class="container">
    <h1>Image Maps Canvas Drawing</h1>
    <form>
    <div class="row">
      <h2> Image 1 </h2>
      	<a href="#" onClick="createJSON();">Generate JSON</a>
        <textarea id="backgroundCords" rows=3 name="coords1" class="canvas-area input-xxlarge" disabled
        placeholder="Shape Coordinates"
        data-image-url="https://generator.canvascompany.nl/configuration/Forex/product/forex-staand/3-4/background.png"></textarea>
    </div>
    </form>
    </div>
    <script language="javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
    <script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/js/bootstrap.min.js"></script>
    <script language="javascript" src="/draw.js"></script>

    <script>
        function createJSON(){
            var backgroundCords = jQuery('#backgroundCords').val();
            var backgroundCords = backgroundCords.split(',');
            postData = {
                "topLeft": {
                    "Sx": 0,
                    "Sy": 0,
                    "Dx": backgroundCords[0],
                    "Dy": backgroundCords[1]
                },
                "topRight": {
                    "Sx": 0,
                    "Sy": 0,
                    "Dx": backgroundCords[2],
                    "Dy": backgroundCords[3]
                },
                "bottomLeft": {
                    "Sx": 0,
                    "Sy": 0,
                    "Dx": backgroundCords[6],
                    "Dy": backgroundCords[7]
                },
                "bottomRight": {
                    "Sx": 0,
                    "Sy": 0,
                    "Dx": backgroundCords[4],
                    "Dy": backgroundCords[5]
                }
            };
            var jsonPretty = JSON.stringify(postData, null, 2);
            console.log(jsonPretty);
        }
    </script>
  </body>
</html>
