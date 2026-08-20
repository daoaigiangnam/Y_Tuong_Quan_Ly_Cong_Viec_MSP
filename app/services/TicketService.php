<?php
declare(strict_types=1);

final class TicketService
{
    public static function create(PDO $db, array $data): int
    {
        $number='INC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $s=$db->prepare('INSERT INTO tickets(ticket_no,customer_id,contract_id,service_id,subject,description,priority,status,owner_user_id,assigned_user_id,created_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$number,$data['customer_id'],$data['contract_id']?:null,$data['service_id']?:null,$data['subject'],$data['description'],$data['priority']?:'P3','NEW',$data['owner_user_id']?:null,$data['assigned_user_id']?:null,$data['created_by_user_id'],now(),now()]);
        $id=(int)$db->lastInsertId();
        self::history($db,$id,$data['created_by_user_id'],'CREATED','NEW',null);
        return $id;
    }
    public static function transition(PDO $db,int $id,string $status,int $userId,?string $note=null): void
    {
        $allowed=['NEW'=>['ASSIGNED','IN_PROGRESS'],'ASSIGNED'=>['IN_PROGRESS','WAITING_CUSTOMER'],'IN_PROGRESS'=>['WAITING_CUSTOMER','RESOLVED','REOPENED'],'WAITING_CUSTOMER'=>['IN_PROGRESS','RESOLVED'],'RESOLVED'=>['CLOSED','REOPENED'],'REOPENED'=>['ASSIGNED','IN_PROGRESS'],'CLOSED'=>[]];
        $s=$db->prepare('SELECT status,reopen_count FROM tickets WHERE id=?'); $s->execute([$id]); $t=$s->fetch(); if(!$t) throw new RuntimeException('Ticket not found');
        if(!in_array($status,$allowed[$t['status']]??[],true)) throw new RuntimeException("Invalid transition {$t['status']} -> {$status}");
        $reopen=(int)$t['reopen_count'] + ($status==='REOPENED'?1:0);
        $s=$db->prepare('UPDATE tickets SET status=?,reopen_count=?,resolved_at=IF(?="RESOLVED",NOW(),resolved_at),closed_at=IF(?="CLOSED",NOW(),closed_at),updated_at=? WHERE id=?');
        $s->execute([$status,$reopen,$status,$status,now(),$id]); self::history($db,$id,$userId,'STATUS_CHANGED',$status,$note);
    }
    public static function history(PDO $db,int $ticketId,int $userId,string $event,string $value,?string $note): void { $s=$db->prepare('INSERT INTO ticket_history(ticket_id,user_id,event,value,note,created_at) VALUES(?,?,?,?,?,?)'); $s->execute([$ticketId,$userId,$event,$value,$note,now()]); }
    public static function comment(PDO $db,int $ticketId,int $userId,string $body,bool $internal=false): void { $s=$db->prepare('INSERT INTO ticket_comments(ticket_id,user_id,body,is_internal,created_at) VALUES(?,?,?,?,?)'); $s->execute([$ticketId,$userId,$body,$internal?1:0,now()]); self::history($db,$ticketId,$userId,'COMMENT',$internal?'INTERNAL':'PUBLIC',$body); }
}
