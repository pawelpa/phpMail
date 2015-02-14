<?php

/* Copyright © ppaste@gmail.com */


//TODO: setMessageBody force mime-type
//TODO: Cc and Bcc check for address duplicates
//TODO: field validation
//TODO: var validation

class phpMail {

    private $subject;
    private $to;
    private $message_body = false;
    private $message_body_mime;
    private $message_file = false;
    private $attachment = false;
    private $attachments; //array(array())
    private $headers;
    private $base64 = false;
    private $encoding = '7bit';
    private $error_message = null;

    public function __construct($to = '', $subject = '', $message = false, $base64 = false) {

//        if (is_null($to)) {
//            throw new Exception('Destination address must be set!');
//        }
        if (is_string($message)) {
            $this->setMessageBody($message);
        }

        $this->to = $to;
        $this->subject = $subject;
        $this->base64 = $base64;
    }

    private function get_mime_info($data) {
        $info = new finfo(FILEINFO_MIME_TYPE);
        return $info->buffer($data);
    }

    private function loadContentFromFile() {
        
        $this->message_body = file_get_contents($this->message_file);

        if ($this->message_body != FALSE) {
            $mime_type = $this->get_mime_info($this->message_body);
            if ($mime_type !== 'text/html') {
                throw new Exception('File is not in html format! MIME_TYPE: ' . $mime_type);
            }
            $this->message_body = preg_replace(array("/\t/", "/\s\s+/"), ' ', $this->message_body);
            $this->message_body = preg_replace(array("/\n\r/", "/\n/", "/\r\n/", "/\r/", "/^\s/", "/^\t/"), '', $this->message_body);
        } else {
            throw new Exception('Error reading file: ' . $this->message_file);
        }
    }

    private function prepareMessage($encoding) {

        $this->message_body_mime = $this->get_mime_info($this->message_body);
        if ($encoding) {
            $this->message_body = chunk_split(base64_encode($this->message_body));
        } else {
            $this->message_body = chunk_split($this->message_body);
        }

        if (!$this->attachment) {
            $this->setCustomHeaders(array(
                'Content-Type:' => '' . $this->message_body_mime . '; charset="UTF-8"',
                'Content-Transfer-Encoding:' => '' . $this->encoding . ''
                    )
            );
        }
    }

    private function writeHeadersFields($headers = array()) {

        if ($headers != null && is_array($headers)) {
            foreach ($headers as $name => $value) {
                $this->writeHeaderField($name, $value);
            }
        }
    }

    private function writeHeaderField($header, $field) {
        $this->headers .= $header . ' ' . $field . "\r\n";
    }

    private function inspectHeader($header) {

        if (!is_string($header)) {
            throw new Exception('object must be string');
        }
        if (!$this->headers) {
            return false;
        }
        return preg_match("/" . $header . "/", $this->headers);
    }

    private function rewriteHeaderField($header, $field) {
        $headers = $this->extractHeadersFields();
        $headers[$header] = $field;
        $this->headers = null;
        $this->writeHeadersFields($headers);
    }

    private function extractHeadersFields() {
        $headers_lines = explode("\r\n", rtrim($this->headers));
        foreach ($headers_lines as $header) {
            $h_temp = explode(": ", $header);
            $headers[$h_temp[0] . ":"] = $h_temp[1];
        }
        return $headers;
    }

    private function appendToHeader($header, $value) {
        $headers = $this->extractHeadersFields();
        $current = $headers[$header];
        $headers[$header] = $current . "," . $value;
        $this->writeHeadersFields($headers);
    }

    public function setCustomHeaders($headers = array()) {
        foreach ($headers as $header => $value) {
            if (!$this->inspectHeader($header)) {
                $this->writeHeadersFields($headers);
            } else {
                $this->rewriteHeaderField($header, $value);
            }
        }
    }

    public function setBase64($enable = true) {
        $this->base64 = $enable;
        $this->encoding = 'base64';
    }

    public function unsetBase64() {
        $this->base64 = false;
        $this->encoding = '7bit';
    }

    private function getBase64() {
        return $this->base64;
    }

    public function setFrom($address, $name = null) {
        $h = "From:";
        if (!empty($name)) {
            $name .= " " . "<{$address}>";
            $address = $name;
        }

        if (!$this->inspectHeader($h)) {
            $this->writeHeaderField($h, $address);
        } else {
            $this->rewriteHeaderField($h, $address);
        }
    }

    public function addCC($address, $name = null) {
        $this->addRecipient($address, $name, 'Cc:');
    }

    public function addBcc($address, $name = null) {
        $this->addRecipient($address, $name, 'Bcc:');
    }

    private function addRecipient($address, $name = null, $type) {

        if (!in_array($type, array('Cc:', 'Bcc:')) || empty($address) || !is_string($address)) {
            throw new Exception('Bad argument type in addRecipient');
        }

        if (!empty($name)) {
            $address = $name . " " . "<{$address}>";
        }

        $h = $type;
        if (!$this->inspectHeader($h)) {
            $this->rewriteHeaderField($h, $address);
        } else {
            $this->appendToHeader($h, $address);
        }
    }

    public function dumpHeaders() {
        var_dump($this->headers);
    }

    public function setReplyTo($address) {
        $h = "Reply-To:";
        if (!$this->inspectHeader($h)) {
            $this->writeHeaderField($h, $address);
        } else {
            $this->rewriteHeaderField($h, $address);
        }
    }

    public function setSubject($subject) {

        $this->subject = $subject;
    }

    public function setTo($address) {
        $to_email = filter_var($address, FILTER_VALIDATE_EMAIL);
        if (!$to_email)
            throw new Exception('email address is not valid!');
        $this->to = $to_email;
    }

    public function eraseMessage() {
        $this->message_body = null;
        $this->message_file = null;
    }

    public function setMessageBody($message) {
        if ($this->message_file != false) {
            throw new Exception('message bod is set by setMesageBodyFromFile function');
        }
        $this->message_body = $message;
    }

    public function setMessageBodyFromFile($file_name) {
        if ($this->message_body != false) {
            throw new Exception('You can only set MessageBody or MessageFile or constructor');
        }

        $this->message_file = $file_name . '.html';
        if (!file_exists($this->message_file)) {
            $this->message_file = false;
            die('File dosn\'t exists!');
        } else {
            if (filetype($this->message_file) == 'file') {
                $this->loadContentFromFile();
            } else {
                die('File type not accepted!');
            }
        }
    }

    public function addAttachment($name, $data = null) {

        if ((!empty($name) && is_string($name)) && (!is_null($data) && $data != FALSE)) {

            $this->attachments[] = array('name' => $name, 'data' => $data, 'size' => strlen($data));
            $this->attachment = true;
        } else {
            throw new Exception("Settup correctly attachment file name and data");
        }
    }

    private function appendAttachments() {

        $semi_rand = sha1(time());
        $main_bundary = "--=main_boundary_{$semi_rand}x";
        $next_bundary = "--=next_bundary_{$semi_rand}x";

        $this->setCustomHeaders(array(
            "Content-type:" => "multipart/mixed; boundary=\"{$main_bundary}\""
        ));

        $temp_body = "--{$main_bundary}" . "\r\n" .
                "Content-type: multipart/related; boundary=\"{$next_bundary}\"" .
                "\r\n" . "\r\n";

        $temp_body .= "--{$next_bundary}" . "\r\n" .
                'Content-type: ' . $this->message_body_mime . '; charset="UTF-8"' . "\r\n" .
                "Content-Transfer-Encoding: {$this->encoding}" . "\r\n\r\n";
        $temp_body .= $this->message_body;

        foreach ($this->attachments as $attachment) {
            $attachment_type = $this->get_mime_info($attachment['data']);
            $attachment_data = "--{$next_bundary}\r\n" .
                    "Content-type: {$attachment_type};" . "\r\n" .
                    "Content-Transfer-Encoding: base64" . "\r\n" .
                    "Content-Disposition: attachment; filename={$attachment['name']}; "
                    . "size={$attachment['size']}" . "\r\n" .
                    "\r\n" .
                    chunk_split(base64_encode($attachment['data']));

            $temp_body .= $attachment_data;
        }
        $this->message_body = $temp_body . "--{$next_bundary}--\n" . "--{$main_bundary}--\n";

        return;
    }

    public function getError() {

        return $this->error_message;
    }

    public function send($debug = false) {

        if (!$this->to)
            throw new Exception('destination email address is not set');

        $this->setCustomHeaders(array('MIME-Version:' => '1.0'));
        $this->prepareMessage($this->getBase64());


        if ($this->attachment) {
            $this->appendAttachments();
        }

        if ($debug) {
            $this->dumpHeaders();
            var_dump($this->message_body);
        }

        if (mail($this->to, $this->subject, $this->message_body, $this->headers)) {
            return true;
        } else {
            $this->error_message = error_get_last();
            return false;
        }
        exit;
    }

}
