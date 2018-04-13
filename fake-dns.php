<?php
/**
 * Created by PhpStorm.
 * User: wangchao
 * Date: 13/04/2018
 * Time: 8:58 AM
 */



class FakeDNSQuery {

    /** @var string */
    private $data;
    /** @var array  */
    private $fakeDnsIpMap;

    /** @var string */
    private $dominio;
    /**
     * FakeDNSQuery constructor.
     * @param string $data
     * @param array $fakeDnsIpMap
     */
    public function __construct($data, $fakeDnsIpMap)
    {

        $this->data = $data;
        $this->fakeDnsIpMap = $fakeDnsIpMap;
        $this->dominio = "";

        $tipo = (ord($data{2}) >> 3) & 15;
        if ($tipo == 0) {
            $ini=12;
            $lon=ord($data{$ini});
            while ($lon != 0) {
                $subDominio = substr($data, $ini+1, $lon);
                $this->dominio .= $subDominio . '.';
                $ini += ($lon+1);
                $lon=ord($data{$ini});
            }
        }

        //echo "get domain $this->dominio" .PHP_EOL;
    }

    public function respuesta()
    {
        $packet='';

        if (isset($this->fakeDnsIpMap[$this->dominio])) {
            var_dump($this->dominio);
            var_dump($this->fakeDnsIpMap);
        }


        if ($this->dominio && isset($this->fakeDnsIpMap[$this->dominio])) {
            $fakeDnsIp = $this->fakeDnsIpMap[$this->dominio];

            echo "start to fake domain $this->dominio to $fakeDnsIp " . PHP_EOL;

            $subStr = substr($this->data, 0, 2);
            $packet .= $subStr . "\x81\x80";
            $subStr = substr($this->data, 4, 2);
            $packet .= $subStr . $subStr . "\x00\x00\x00\x00";
            $subStr = substr($this->data, 12);
            $packet .= $subStr;
            $packet .= "\xc0\x0c";
            $packet .= "\x00\x01\x00\x01\x00\x00\x00\x3c\x00\x04";

            $ipSubStr = "";
            foreach (explode(".", $fakeDnsIp) as $perIpSeg) {
                $ipSubStr .= chr(intval($perIpSeg));
            }
            $packet .= $ipSubStr;
        }
        return $packet;
    }
}



$udpSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

$hostIp = '192.168.1.122';
socket_bind($udpSocket, $hostIp, 53);


//add the fake dns you want bellow:
$fakeDnsIpMap = [
    //..
    "my-domain.test" => '192.168.1.122',
    "my-domain2.test" => '192.168.1.168',
    //..
];

while (1) {
    $fromIp = '';
    $fromPort = null;
    socket_recvfrom($udpSocket, $dnsQueryData, 1024, 0, $fromIp, $fromPort);
    //echo "from remote address $fromIp and remote port $fromPort" . PHP_EOL;

    $dq= new FakeDNSQuery($dnsQueryData,$fakeDnsIpMap);
    $respuestaData = $dq->respuesta();
    if ($respuestaData) {
        socket_sendto($udpSocket, $respuestaData, strlen($respuestaData),0, $fromIp, $fromPort);
    }

    echo PHP_EOL;
}

