<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SMTP MX
 *
 * SMTP MX implementation of the PEAR Mail interface. Requires the Net_SMTP class.
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2010-2017 gERD Schaufelberger
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its
 *    contributors may be used to endorse or promote products derived from
 *    this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Mail
 * @package    Mail_smtpmx
 * @author     gERD Schaufelberger <gerd@php-tools.net>
 * @copyright  2010-2017 gERD Schaufelberger
 * @license    http://opensource.org/licenses/BSD-3-Clause New BSD License
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Mail/
 */

/**
 * SMTP MX implementation of the PEAR Mail interface. Requires the Net_SMTP class.
 *
 *
 * @access public
 * @author  gERD Schaufelberger <gerd@php-tools.net>
 * @package Mail
 * @version $Revision$
 */
class Mail_smtpmx extends Mail {

    /**
     * SMTP connection object.
     *
     * @var object
     * @access private
     */
    public $_smtp = null;

    /**
     * The port the SMTP server is on.
     * @var integer
     * @see getservicebyname()
     */
    public $port = 25;

    /**
     * Hostname or domain that will be sent to the remote SMTP server in the
     * HELO / EHLO message.
     *
     * @var string
     * @see posix_uname()
     */
    public $mailname = 'localhost';

    /**
     * SMTP connection timeout value.  NULL indicates no timeout.
     *
     * @var integer
     */
    public $timeout = 10;

    /**
     * use either PEAR:Net_DNS or getmxrr
     *
     * @var boolean
     */
    public $withNetDns = true;

    /**
     * PEAR:Net_DNS_Resolver
     *
     * @var object
     */
    public $resolver;

    /**
     * Whether to use VERP or not. If not a boolean, the string value
     * will be used as the VERP separators.
     *
     * @var mixed boolean or string
     */
    public $verp = false;

    /**
     * Whether to use VRFY or not.
     *
     * @var boolean $vrfy
     */
    public $vrfy = false;

    /**
     * Switch to test mode - don't send emails for real
     *
     * @var boolean $debug
     */
    public $test = false;

    /**
     * Turn on Net_SMTP debugging?
     *
     * @var boolean $peardebug
     */
    public $debug = false;

    /**
     * internal error codes
     *
     * translate internal error identifier to PEAR-Error codes and human
     * readable messages.
     *
     * @var boolean $debug
     * @todo as I need unique error-codes to identify what exactly went wrond
     *       I did not use intergers as it should be. Instead I added a "namespace"
     *       for each code. This avoids conflicts with error codes from different
     *       classes. How can I use unique error codes and stay conform with PEAR?
     */
    public $errorCode = array(
        'not_connected' => array(
            'code'  => 1,
            'msg'   => 'Could not connect to any mail server ({HOST}) at port {PORT} to send mail to {RCPT}.'
        ),
        'failed_vrfy_rcpt' => array(
            'code'  => 2,
            'msg'   => 'Recipient "{RCPT}" could not be veryfied.'
        ),
        'failed_set_from' => array(
            'code'  => 3,
            'msg'   => 'Failed to set sender: {FROM}.'
        ),
        'failed_set_rcpt' => array(
            'code'  => 4,
            'msg'   => 'Failed to set recipient: {RCPT}.'
        ),
        'failed_send_data' => array(
            'code'  => 5,
            'msg'   => 'Failed to send mail to: {RCPT}.'
        ),
        'no_from' => array(
            'code'  => 5,
            'msg'   => 'No from address has be provided.'
        ),
        'send_data' => array(
            'code'  => 7,
            'msg'   => 'Failed to create Net_SMTP object.'
        ),
        'no_mx' => array(
            'code'  => 8,
            'msg'   => 'No MX-record for {RCPT} found.'
        ),
        'no_resolver' => array(
            'code'  => 9,
            'msg'   => 'Could not start resolver! Install PEAR:Net_DNS or switch off "netdns"'
        ),
        'failed_rset' => array(
            'code'  => 10,
            'msg'   => 'RSET command failed, SMTP-connection corrupt.'
        ),
    );

    /**
     * Constructor.
     *
     * Instantiates a new Mail_smtp:: object based on the parameters
     * passed in. It looks for the following parameters:
     *     mailname    The name of the local mail system (a valid hostname which matches the reverse lookup)
     *     port        smtp-port - the default comes from getservicebyname() and should work fine
     *     timeout     The SMTP connection timeout. Defaults to 30 seconds.
     *     vrfy        Whether to use VRFY or not. Defaults to false.
     *     verp        Whether to use VERP or not. Defaults to false.
     *     test        Activate test mode? Defaults to false.
     *     debug       Activate SMTP and Net_DNS debug mode? Defaults to false.
     *     netdns      whether to use PEAR:Net_DNS or the PHP build in function getmxrr, default is true
     *
     * If a parameter is present in the $params array, it replaces the
     * default.
     *
     * @access public
     * @param array Hash containing any parameters different from the
     *              defaults.
     * @see _Mail_smtpmx()
     */
    public function __construct($params)
    {
        if (isset($params['mailname'])) {
            $this->mailname = $params['mailname'];
        } else {
            // try to find a valid mailname
            if (function_exists('posix_uname')) {
                $uname = posix_uname();
                $this->mailname = $uname['nodename'];
            }
        }

        // port number
        if (isset($params['port'])) {
            $this->_port = $params['port'];
        } else {
            $this->_port = getservbyname('smtp', 'tcp');
        }

        if (isset($params['timeout'])) $this->timeout = $params['timeout'];
        if (isset($params['verp'])) $this->verp = $params['verp'];
        if (isset($params['test'])) $this->test = $params['test'];
        if (isset($params['peardebug'])) $this->test = $params['peardebug'];
        if (isset($params['netdns'])) $this->withNetDns = $params['netdns'];
    }

    /**
     * Destructor implementation to ensure that we disconnect from any
     * potentially-alive persistent SMTP connections.
     */
    public function __destruct()
    {
        if (is_object($this->_smtp)) {
            $this->_smtp->disconnect();
            $this->_smtp = null;
        }
    }

    /**
     * Implements Mail::send() function using SMTP direct delivery
     *
     * @access public
     * @param mixed $recipients in RFC822 style or array
     * @param array $headers The array of headers to send with the mail.
     * @param string $body The full text of the message body,
     * @return mixed Returns true on success, or a PEAR_Error
     */
    public function send($recipients, array $headers, $body)
    {
        $this->_sanitizeHeaders($headers);

        // Prepare headers
        list($from, $textHeaders) = $this->prepareHeaders($headers);

        // use 'Return-Path' if possible
        if (!empty($headers['Return-Path'])) {
            $from = $headers['Return-Path'];
        }
        if (!isset($from)) {
            throw new RuntimeException('no_from');
        }

        // Prepare recipients
        $recipients = $this->parseRecipients($recipients);

        foreach ($recipients as $rcpt) {
            list($user, $host) = explode('@', $rcpt);

            $mx = $this->_getMx($host);

            if (empty($mx)) {
                $info = array('rcpt' => $rcpt);
                throw new RuntimeException('no_mx', $info);
            }

            $connected = false;
            foreach ($mx as $mserver => $mpriority) {
                $this->_smtp = new Net_SMTP($mserver, $this->port, $this->mailname);

                // configure the SMTP connection.
                if ($this->debug) {
                    $this->_smtp->setDebug(true);
                }

                try {
                    $res = $this->_smtp->connect($this->timeout);
                } catch (\Exception $e) {
                    continue;
                }

                // connection established
                if ($res) {
                    $connected = true;
                    break;
                }
            }

            if (!$connected) {
                $info = array(
                    'host' => implode(', ', array_keys($mx)),
                    'port' => $this->port,
                    'rcpt' => $rcpt,
                );
                throw new RuntimeException('not_connected', $info);
            }

            // Verify recipient
            if ($this->vrfy) {
                $this->_smtp->vrfy($rcpt);
            }

            // mail from:
            $args['verp'] = $this->verp;
            $this->_smtp->mailFrom($from, $args);

            // rcpt to:
            $this->_smtp->rcptTo($rcpt);

            // Don't send anything in test mode
            if ($this->test) {
                $this->_smtp->rset();
                $this->_smtp->rset();

                $this->_smtp->disconnect();
                $this->_smtp = null;

                return true;
            }

            // Send data
            $this->_smtp->data($body, $textHeaders);

            $this->_smtp->disconnect();
            $this->_smtp = null;
        }

        return true;
    }

    /**
     * Recieve mx rexords for a spciefied host
     *
     * The MX records
     *
     * @access private
     * @param string $host mail host
     * @return mixed sorted
     */
    public function _getMx($host)
    {
        $mx = array();

        if ($this->withNetDns) {
            $this->_loadNetDns();

            $response = $this->resolver->query($host, 'MX');
            if (!$response) {
                return false;
            }

            foreach ($response->answer as $rr) {
                if ($rr->type == 'MX') {
                    $mx[$rr->exchange] = $rr->preference;
                }
            }
        } else {
            $mxHost = array();
            $mxWeight = array();

            if (!getmxrr($host, $mxHost, $mxWeight)) {
                return false;
            }
            for ($i = 0; $i < count($mxHost); ++$i) {
                $mx[$mxHost[$i]] = $mxWeight[$i];
            }
        }

        asort($mx);

        return $mx;
    }

    /**
     * initialize PEAR:Net_DNS_Resolver
     *
     * @access private
     * @return boolean true on success
     */
    public function _loadNetDns()
    {
        if (is_object($this->resolver)) {
            return true;
        }

        $this->resolver = new Net_DNS_Resolver();
        if ($this->debug) {
            $this->resolver->test = 1;
        }

        return true;
    }
}
