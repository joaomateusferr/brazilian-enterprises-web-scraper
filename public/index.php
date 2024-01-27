<?php

    if(empty($_POST['Query'])){

        ?>

            <!DOCTYPE html>

            <html>

                <head>

                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet">
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>

                    <style>

                        body {
                            margin: 0;
                        }

                        div.main {
                            padding: 1px 16px;
                        }

                        div.center {
                            align-items: center;
                            display: flex;
                            flex-direction: row;
                            flex-wrap: wrap;
                            justify-content: center;
                        }

                        div.container {
                            max-width: 100%;
                            margin-top: 1%;
                            margin-bottom: 1%;
                        }

                        div.row, div.form-row {
                            width: 100%;
                            margin-top: 1%;
                            margin-bottom: 2%;
                        }

                        textarea, select {
                            width: 100%;
                            margin-top: 1%;
                            margin-bottom: 1%;
                            border: 3px solid #003060;
                            resize: none !important;
                        }

                        button{
                            background-color:#003060;
                            color:white;
                            font-size: 18;
                            border: none;
                            border: 2px solid #003060;
                            width: 28%;
                            padding-top: 10px;
                            padding-right: 10px;
                            padding-bottom: 10px;
                            padding-left: 10px;
                        }

                    </style>

                </head>

                <body>

                    <div class="main">

                        <div class="container">

                            <div id="DivSearch">

                                <form id="FormSearch" action="<?php echo $_SERVER['PHP_SELF'];?>" onsubmit="showLoaring()" method="post">

                                    <div class="form-row">

                                        <div class="col-sm-12">

                                            <textarea id="Query" name="Query" rows="20"> </textarea>

                                        </div>

                                    </div>

                                    <div class="form-row">

                                        <div class="col-sm-12 center">

                                            <button type="submit"> Search </button>

                                        </div>

                                    </div>

                                </form>

                            </div>

                            <div id="DivProcessing" style="display:none;">
                                <p>The search is being processed, this may take a while, don't close the browser and your csv will soon be downloaded</p>
                            </div>

                        </div>

                    </div>

                    <script>

                        function showLoaring(){

                            document.getElementById("DivSearch").style.display = "none";
                            document.getElementById("DivProcessing").style.display = "flex";

                        }

                    </script>

                </body>

            </html>

        <?php

    } else {

        $ProjectPath = explode("/", $_SERVER['DOCUMENT_ROOT']);
	    unset($ProjectPath[array_key_last($ProjectPath)]);
	    $ProjectPath = implode("/", $ProjectPath);

        function isJson(string $String) {

            json_decode($String);
            return json_last_error() === JSON_ERROR_NONE;

        }

        function createJson (string $Query) : string {

            $JsonExportPath = '/tmp/brazilian-enterprise-details-web-scraper-'.uniqid().'.json';
            file_put_contents($JsonExportPath, $Query);
            return $JsonExportPath;

        }

        function downloadCsv (string $CsvPath) : void {

            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename='.basename($CsvPath));
            header('Pragma: no-cache');
            readfile($CsvPath);

        }

        if(!isJson($_POST['Query']))
            exit('Invalid json query!');

        $JsonPath = createJson($_POST['Query']);

        $Output = [];
        $ResultCode = 0;

        $Command = "php $ProjectPath/scripts/get-data.php $JsonPath";

        exec($Command, $Output, $ResultCode);

        if($ResultCode != 0 || empty($Output))
            exit('Something went wrong!');

        $CsvPath = $Output[0];

        downloadCsv($CsvPath);
        unlink($CsvPath);

    }