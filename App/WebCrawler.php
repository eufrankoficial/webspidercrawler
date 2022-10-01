<?php

namespace App;


class WebCrawler
{
    protected $cookie = __DIR__."/../rand/cookie";
    protected $baseUrl = "www.sintegra.fazenda.pr.gov.br";

    protected $cnpj;
    protected $captcha;
    protected $resultado;

    public function iniciar()
    {
        return $this->limparCookies()
            ->gerarCaptcha()
            ->lerCaptcha()
            ->lerCnpj()
            ->consultarCnpj();
    }

    private function consultarCnpj()
    {
        $data[] = $this->buscarInformacoes($this->getSintegraUrl(), [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->getDadosDeConsulta(),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST
        ])->formatarResultado();

        $this->buscarIE($this->resultado, $data);

        print_r($data);

        return $this;
    }

    private function buscarIE($html, &$data = [])
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        if(!($formInfo = $dom->getElementById("Sintegra1CampoAnterior")) || is_null($dom->getElementById("consultar"))) {
            return $data;
        }

        $data[] = $this->buscarInformacoes($this->getUrlParaOutraIE(), [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                '_method' => 'POST',
                'data[Sintegra1][campoAnterior]' => $formInfo->getAttribute('value'),
                'consultar' => ''
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST
        ])->formatarResultado();

        return $this->buscarIE($this->resultado, $data);
    }

    private function limparCookies()
    {
        $this->removerArquivo($this->cookie);

        return $this;
    }

    private function gerarCaptcha()
    {
        $this->buscarInformacoes($this->baseUrl);

        $this->buscarInformacoes($this->getRecaptchaUrl());
        $this->salvarCaptcha(
            $this->resultado
        );

        return $this;
    }

    private function salvarCaptcha($img, $arquivo = 'rand/captcha.jpeg')
    {
        $this->removerArquivo($arquivo);

        $fp = fopen($arquivo, 'x');
        fwrite($fp, $img);
        fclose($fp);
    }

    private function buscarInformacoes($url, $opts = [])
    {
        $curl = curl_init($url);
        $default = [
            CURLOPT_HTTPHEADER => $this->getHttpHeader(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_COOKIEFILE => $this->cookie,
            CURLOPT_COOKIEJAR => $this->cookie
        ];

        $default = array_replace($default, $opts);
        curl_setopt_array($curl, $default);
        $resultado = curl_exec($curl);
        curl_close($curl);

        $this->resultado = $resultado;
        return $this;
    }

    private function formatarResultado()
    {
        $data = [];
        $dom = new \DOMDocument;
        @$dom->loadHTML($this->resultado);
        $finder = new \DOMXPath($dom);
        $tds = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' form_label ')]|//*[contains(concat(' ', normalize-space(@class), ' '), ' erro_label ')]");

        foreach ($tds as $td) {
            $name = trim(str_replace(":", "", $td->nodeValue));
            if(!in_array($name, ["SPED (EFD, NF-e, CT-e)", "Recomendação"])) {
                $data[$name] = trim($td->nextSibling->nextSibling->nodeValue);
            }
        }

        return $data;
    }
    private function lerCaptcha()
    {
        $this->setCaptcha(readline("Informe o captcha: "));
        return $this;
    }
    
    private function setCaptcha($captcha)
    {
        $this->captcha = $captcha;
    }

    public function getCaptcha()
    {
        return $this->captcha;
    }

    private function lerCnpj()
    {
        $this->setCnpj(readline("Informe o CNPJ que deseja consultar: "));
        return $this;
    }

    private function setCnpj($cnpj)
    {
        $this->cnpj = $cnpj;
    }

    public function getCnpj()
    {
        return $this->cnpj;
    }

    private function removerArquivo($arquivo)
    {
        if(file_exists($arquivo)) {
            unlink($arquivo);
        }
    }

    private function getDadosDeConsulta()
    {
        return [
            '_method' => 'POST',
            'data[Sintegra1][CodImage]' => $this->getCaptcha(),
            'data[Sintegra1][Cnpj]' => $this->getCnpj(),
            'empresa' => 'Consultar Empresa'
        ];
    }

    private function getHttpHeader()
    {
        return [
            "Pragma: no-cache",
            "Origin: ".$this->baseUrl,
            "Host: ".$this->baseUrl,
            "User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: pt-BR,pt;q=0.8,en-US;q=0.5,en;q=0.3",
            "Referer: ".$this->getRefererUrl(),
            "Connection: keep-alive"
        ];
    }

    private function getRecaptchaUrl()
    {
        return $this->baseUrl . "/sintegra/captcha";
    }

    private function getRefererUrl()
    {
        return $this->baseUrl . "/sintegra/sintegra1";
    }

    private function getSintegraUrl()
    {
        return $this->baseUrl . "/sintegra/";
    }

    private function getUrlParaOutraIE()
    {
        return $this->baseUrl . "/sintegra/sintegra1/consultar";
    }

}
