<?php
if (!isset($_POST['from']) || !isset($_POST['subject']) || !isset($_POST['to']) || !isset($_POST['auth_code'])) {
    header('Content-type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8"/>
        <style>
        p  {
            padding-left: 10px;
            padding-right: 20px;
        }
        fieldset {
            width: 300px;
        }
        </style>
    </head>
    <body>
        <form method="POST" action="">
            <fieldset>
            <p>
            <label>From: </label>
            <input required type="text" name="from"/><br/>
            </p>
            <p>
            <label>Subject:</label>
            <input required type="text" name="subject"/><br/>
            </p>
            <p>
            <label>To:</label>
            <input required type="text" name="to"/><br/>
            </p>
            <p>
            <label>base64</label>
            <input type="checkbox" name="base64"/>
            </p>
            <p>
            <label>Auth code:</label>
            <input required type="password" name="auth_code"/><br/>
            </p>
            <button type="submit">Wyślij</button>
            </fieldset>
        </form>
    </body>
</html>';
} else {
    require 'config.php';
    require LIB . 'phpMail.class.php';

    //TODO: filter $_POST vars
    $subject = $_POST['subject'];
    $from = $_POST['from'];
    $to = $_POST['to'];
    
    $base64 = false;
    if(isset($_POST['base64']) && $_POST['base64'] == 'on') {
        $base64 = true;
    }
       
    if (md5($_POST['auth_code']) != "1667ad5c953c679e70dd920ccddb208f") {
        echo '<script language="javascript">alert("wrong auth code");window.location="index.php";</script>';
        
    } else {
        $mail = new phpMail($to, $subject);
        $mail->setMessageBodyFromFile('./html');
        $mail->setBase64();
        $mail->setCustomHeaders(array('From:' => 'unknown@example.com',
                                'Reply-to:' => $from,
                                'MIME-Version:' => '1.0',
                                'Content-type:' => 'text/html; charset="utf-8"'
                                )
                        );
        echo $mail->send(true);
    }
}
?>