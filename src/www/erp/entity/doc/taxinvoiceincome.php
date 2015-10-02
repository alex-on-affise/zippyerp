<?php

namespace ZippyERP\ERP\Entity\Doc;

use \ZippyERP\System\System;
use \ZippyERP\ERP\Util;
use \ZippyERP\ERP\Entity\Entry;
use \ZippyERP\ERP\Entity\MoneyFund;
use \ZippyERP\ERP\Helper as H;

/**
 * Класс-сущность  документ входящая налоговая  накладая
 *
 */
class TaxInvoiceIncome extends Document
{

    public function generateReport()
    {


        $i = 1;
        $detail = array();

        foreach ($this->detaildata as $value) {
            $detail[] = array("no" => $i++,
                "itemname" => $value['itemname'],
                "measure" => $value['measure_name'],
                "quantity" => $value['quantity'] / 1000,
                "price" => H::fm($value['price']),
                "pricends" => H::fm($value['pricends']),
                "amount" => H::fm(($value['quantity'] / 1000) * $value['price'])
            );
        }

        $firm = \ZippyERP\System\System::getOptions("firmdetail");

        $header = array('date' => date('d.m.Y', $this->document_date),
            "firmname" => $firm['name'],
            "firmcode" => $firm['code'],
            "customername" => $this->headerdata["customername"],
            "document_number" => $this->document_number,
            "totalnds" => H::fm($this->headerdata["totalnds"]),
            "total" => H::fm($this->headerdata["total"])
        );

        $report = new \ZippyERP\ERP\Report('taxinvoiceincome.tpl');

        $html = $report->generate($header, $detail);

        return $html;
    }

    public function Execute()
    {
        
    }

    /**
     * Импорт из  ГНАУ  формат XML...
     *
     * @param mixed $data
     * @return {mixed|TaxInvoiceIncome}    Документ  или  строку  с ошибкой
     */
    public static function import($data)
    {
        if (strpos($data, "<DECLARHEAD>") == false) {
            return "Неверный формат";
        }
        $data = iconv("WINDOWS-1251", "UTF-8", $data);
        $data = str_replace("windows-1251", "utf-8", $data);
        $xml = @simplexml_load_string($data);
        if ($xml instanceof \SimpleXMLElement) {
            
        } else {
            return "Неверный формат";
        }

        $type = (string) $xml->DECLARHEAD->C_DOC . (string) $xml->DECLARHEAD->C_DOC_SUB;
        if ($type != "J12010" && $type != "F12010") {
            return "Тип  документа  не  Налоговая накладная";
        }


        $doc = new TaxInvoiceIncome();

        $date = (string) $xml->DECLARBODY->HFILL;
        $date = substr($date, 4, 4) . '-' . substr($date, 2, 2) . '-' . substr($date, 0, 2);
        $doc->document_date = strtotime($date);
        $doc->document_number = (string) $xml->DECLARBODY->HNUM;
        $doc->headerdata['based'] = (string) $xml->DECLARBODY->H01G1S;
        $inn = (string) $xml->DECLARBODY->HKSEL;
        $customer = \ZippyERP\ERP\Entity\Customer::loadByInn($inn);
        if ($customer == null) {
            return "Не найден  контрагент  с  ИНН " . $inn;
        }
        $doc->headerdata['customer'] = $customer->customer_id;
        $ernn = (string) $xml->DECLARBODY->HERPN;
        if ($ernn == true) {
            $doc->headerdata['ernn'] = true;
        }


        $details = array();
        foreach ($xml->xpath('//RXXXXG3S') as $node) {
            $details[(string) $node->attributes()->ROWNUM]['name'] = (string) $node;
        }
        foreach ($xml->xpath('//RXXXXG5') as $node) {
            $details[(string) $node->attributes()->ROWNUM]['qty'] = (string) $node;
        }
        foreach ($xml->xpath('//RXXXXG6') as $node) {
            $details[(string) $node->attributes()->ROWNUM]['price'] = (string) $node;
        }
        foreach ($xml->xpath('//RXXXXG105_2S') as $node) {
            $details[(string) $node->attributes()->ROWNUM]['mcode'] = (string) $node;
        }
        foreach ($xml->xpath('//RXXXXG4') as $node) {
            $details[(string) $node->attributes()->ROWNUM]['code'] = (string) $node;
        }
        foreach ($xml->xpath('//RXXXXG4S') as $node) {
            $details[(string) $node->attributes()->ROWNUM]['mname'] = (string) $node;
        }
        $nds = H::nds();
        $doc->detaildata = array();
        foreach ($details as $row) {
            if ($row['code'] > 0) {
                $item = \ZippyERP\ERP\Entity\Item::loadByUktzed($row['code']);
                if ($item == null) {
                    return "Не найден  ТМЦ с  кодом  УКТ ЗЕД: " . $row['code'];
                }
                $item->price = $row['price'] * 100;
                $item->pricends = $item->price + $item->price * $nds;
                $item->quantity = $row['qty'];
                $doc->detaildata[] = $item;
                continue;
            }
            // Пытаемся  найти  по имени
            $item = \ZippyERP\ERP\Entity\Item::getFirst("itemname='" . trim($row['price']) . "'");
            if ($item != null) {
                $item->price = $row['price'] * 100;
                $item->quantity = $row['qty'];
                $doc->detaildata[] = $item;
                continue;
            }
        }
        if (count($details) > count($doc->detaildata)) {
            return "Не найдены  все  записи  таблицы";
        }

        return $doc;
    }

}
