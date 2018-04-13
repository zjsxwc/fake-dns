# python-fake-dns

# 第0步

开启vagrant虚拟机，该虚拟机ip地址为`192.168.1.168`，该虚拟机提供对域名`my-domain.test`的网页服务

# 第1步

宿主机运行下面这个php或者python进行fake dns，宿主机ip是`192.168.1.122`

```php
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



```



```python

import socket

class DNSQuery:
  def __init__(self, data):
    self.data=data
    self.dominio=''

    tipo = (ord(data[2]) >> 3) & 15   # Opcode bits
    if tipo == 0:                     # Standard query
      ini=12
      lon=ord(data[ini])
      while lon != 0:
        self.dominio+=data[ini+1:ini+lon+1]+'.'
        ini+=lon+1
        lon=ord(data[ini])

  def respuesta(self, ip):
    packet=''
    if self.dominio:
      packet+=self.data[:2] + "\x81\x80"
      packet+=self.data[4:6] + self.data[4:6] + '\x00\x00\x00\x00'   # Questions and Answers Counts
      packet+=self.data[12:]                                         # Original Domain Name Question
      packet+='\xc0\x0c'                                             # Pointer to domain name
      packet+='\x00\x01\x00\x01\x00\x00\x00\x3c\x00\x04'             # Response type, ttl and resource data length -> 4 bytes
      packet+=str.join('',map(lambda x: chr(int(x)), ip.split('.'))) # 4bytes of IP
    return packet

if __name__ == '__main__':
  ip='192.168.1.168'   #<---------------------------------------把这里改成目标局域网地址就行
  print 'pyminifakeDNS:: dom.query. 60 IN A %s' % ip
  
  udps = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
  udps.bind(('',53))
  
  try:
    while 1:
      data, addr = udps.recvfrom(1024)
      p=DNSQuery(data)
      udps.sendto(p.respuesta(ip), addr)
      print 'Respuesta: %s -> %s' % (p.dominio, ip)
  except KeyboardInterrupt:
    print 'Finalizando'
    udps.close()


```


# 第2步

在与宿主机同局域网的安卓手机里，修改dns为宿主机地址`192.168.1.122`

# 第3步

安卓手机就能在浏览器里访问到`http://my-domain.test`了



