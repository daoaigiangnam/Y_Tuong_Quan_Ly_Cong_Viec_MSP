<?php
declare(strict_types=1);

final class ContractAlertService
{
    public static function run(PDO $db,array $config): array
    {
        $rows=$db->query("SELECT c.*,cu.name customer_name,cu.email customer_email,uo.email owner_email,ul.email lead_email,us.email sales_email FROM contracts c JOIN customers cu ON cu.id=c.customer_id LEFT JOIN users uo ON uo.id=c.owner_user_id LEFT JOIN users ul ON ul.id=c.lead_user_id LEFT JOIN users us ON us.id=c.sales_user_id WHERE c.status='ACTIVE' AND c.end_date>=CURDATE()")->fetchAll();
        $sent=[];
        foreach($rows as $c){
            $rules=$db->prepare('SELECT * FROM contract_alert_rules WHERE contract_id=? AND is_active=1 ORDER BY alert_no'); $rules->execute([$c['id']]);
            foreach($rules->fetchAll() as $r){
                $target=(new DateTime($c['end_date']))->modify('-'.(int)$r['days_before'].' days')->format('Y-m-d');
                if($target!==date('Y-m-d')) continue;
                $chk=$db->prepare('SELECT id FROM contract_alerts WHERE contract_id=? AND alert_no=? AND sent_at IS NOT NULL LIMIT 1'); $chk->execute([$c['id'],$r['alert_no']]); if($chk->fetch()) continue;
                $subject='CẢNH BÁO HỢP ĐỒNG - Lần '.$r['alert_no'].' - '.$c['contract_no'];
                $body="Kính gửi Anh/Chị,\n\nHợp đồng {$c['contract_no']} - {$c['customer_name']} sẽ hết hạn ngày {$c['end_date']}.\nĐây là cảnh báo lần {$r['alert_no']} (trước {$r['days_before']} ngày).\n\nVui lòng kiểm tra kế hoạch gia hạn.\n\nMSP ITSM";
                $to=$c['owner_email'] ?: $c['lead_email']; $cc=implode(',',array_filter([$c['lead_email'],$c['sales_email']]));
                $ok=$to ? mail_notice($config,$to,$subject,$body,$cc) : false;
                $s=$db->prepare('INSERT INTO contract_alerts(contract_id,alert_no,scheduled_date,sent_at,status,recipient,cc,error_message,created_at) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE sent_at=VALUES(sent_at),status=VALUES(status),recipient=VALUES(recipient),cc=VALUES(cc),error_message=VALUES(error_message)');
                $s->execute([$c['id'],$r['alert_no'],$target,$ok?now():null,$ok?'SENT':'FAILED',$to,$cc,$ok?null:'No recipient or mail() failed',now()]);
                $sent[]=['contract'=>$c['contract_no'],'alert'=>$r['alert_no'],'status'=>$ok?'SENT':'FAILED'];
            }
        }
        return $sent;
    }
}
