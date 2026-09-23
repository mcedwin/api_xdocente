<?php
/**
 * Validación local de la cascada en destroyStudent.
 * Creación vía API (curso/unidad/estudiante/sesión/trabajo grupal+grupo),
 * alerta y score insertados directos en BD, borrado del estudiante y
 * verificación API + BD. Al final limpia TODOS los datos de prueba.
 */
$BASE = 'http://127.0.0.1:8080/api';

$PDO = new PDO('mysql:host=127.0.0.1;port=3306;dbname=xdocente2;charset=utf8mb4', 'root', '');
$PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function uuid4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    $h = bin2hex($d);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

function q(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function api(string $method, string $path, ?array $body, string $token): array {
    $ch = curl_init('http://127.0.0.1:8080/api' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP $method $path: $err");
    }
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp, true) ?? $resp];
}

function check(array $r, string $label): void {
    if ($r['code'] < 200 || $r['code'] >= 300) {
        echo "  [$label] HTTP {$r['code']} FAIL: " . json_encode($r['body']) . "\n";
        throw new RuntimeException("Fallo en $label");
    }
    echo "  [$label] HTTP {$r['code']} OK\n";
}

function first(array $arr, callable $fn) {
    foreach ($arr as $x) {
        if ($fn($x)) return $x;
    }
    return null;
}

// ── datos de prueba ──
$courseId = uuid4();
$unitId   = uuid4();
$stuUuid  = uuid4();
$sesUuid  = uuid4();
$gwUuid   = uuid4();
$grpUuid  = uuid4();
$critUuid = uuid4();
$estId = null; $userId = null;

try {
    // login
    $login = api('POST', '/login', ['email' => 'mcedwin@gmail.com', 'password' => 'debug2026'], '');
    if (($login['body']['token'] ?? '') === '') {
        echo "FAIL login\n";
        exit(1);
    }
    $token = $login['body']['token'];
    $userId = $login['body']['user']['id'] ?? null;

    echo "1) curso\n";
    check(api('POST', '/courses', ['id' => $courseId, 'name' => 'Test Cascada ' . date('His')], $token), 'POST /courses');

    echo "2) unidad\n";
    check(api('POST', "/courses/$courseId/units", ['id' => $unitId, 'name' => 'Unidad 1'], $token), 'POST units');

    echo "3) estudiante\n";
    check(api('POST', "/courses/$courseId/students", ['id' => $stuUuid, 'name' => 'Estudiante Prueba'], $token), 'POST students');

    $row = q($PDO, 'SELECT id FROM app_students WHERE uuid = ?', [$stuUuid]);
    $estId = $row[0]['id'] ?? null;
    echo "  estudiante db id=$estId\n";

    echo "4) sesión con asistencia\n";
    $session = [
        'id' => $sesUuid,
        'date' => date('Y-m-d'),
        'records' => [[
            'studentId' => $stuUuid,
            'studentName' => 'Estudiante Prueba',
            'attendance' => 'absent',
            'observations' => null,
        ]],
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'sync_status' => 'synced',
        'device_id' => null,
    ];
    check(api('POST', "/courses/$courseId/units/$unitId/sessions", $session, $token), 'POST sessions');

    echo "5) trabajo grupal (rúbrica) + grupo con miembro\n";
    check(api('POST', "/courses/$courseId/units/$unitId/group-works", [
        'id' => $gwUuid, 'name' => 'TG Prueba', 'usesRubric' => true,
        'criteria' => [['id' => $critUuid, 'name' => 'Criterio 1', 'maxScore' => 10]],
    ], $token), 'POST group-works');
    check(api('POST', "/courses/$courseId/units/$unitId/group-works/$gwUuid/groups", [
        'id' => $grpUuid, 'name' => 'Grupo A', 'studentIds' => [$stuUuid],
    ], $token), 'POST groups');

    // alerta + score individual insertados directos (cobertura de cascada)
    $uid = $userId ?? 1;
    $courseRow = q($PDO, 'SELECT id FROM app_courses WHERE uuid = ?', [$courseId]);
    $cId = $courseRow[0]['id'] ?? null;
    q($PDO, "INSERT INTO app_alerts (uuid, usuario_id, estudiante_id, curso_id, tipo, descripcion, fecha, leida, created_at, updated_at, sync_status)
             VALUES (?, ?, ?, ?, 'baja_participacion', 'Prueba cascada', ?, 0, ?, ?, 'synced')",
        [uuid4(), $uid, $estId, $cId, date('Y-m-d'), date('c'), date('c')]);
    echo "  alerta insertada (curso_id=$cId)\n";

    $act = q($PDO, "SELECT id FROM app_activities WHERE uuid = ? LIMIT 1", [$gwUuid]);
    $crit = q($PDO, "SELECT id FROM app_activity_criteria WHERE uuid = ? LIMIT 1", [$critUuid]);
    if ($act && $crit) {
        q($PDO, "INSERT INTO app_activity_scores (uuid, activity_id, criterio_id, estudiante_id, grupo_id, puntaje, sync_status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NULL, 7.00, 'synced', ?, ?)",
            [uuid4(), $act[0]['id'], $crit[0]['id'], $estId, date('c'), date('c')]);
        echo "  score individual insertado (activity={$act[0]['id']})\n";
    } else {
        echo "  AVISO: no se insertó score (act=" . json_encode($act) . ", crit=" . json_encode($crit) . ")\n";
    }

    echo "6) GET curso (pre-borrado)\n";
    $r = api('GET', "/courses/$courseId", null, $token);
    $course = $r['body']['data'] ?? null;
    if (!$course) {
        echo "  FAIL GET curso\n";
        throw new RuntimeException('GET curso sin data');
    }
    $unit = first($course['units'] ?? [], fn($u) => ($u['id'] ?? '') === $unitId);
    $ses  = first($unit['sessions'] ?? [], fn($s) => ($s['id'] ?? '') === $sesUuid);
    $rec  = first($ses['records'] ?? [], fn($rec) => ($rec['studentId'] ?? '') === $stuUuid);
    $gw   = first($unit['groupWorks'] ?? [], fn($g) => ($g['id'] ?? '') === $gwUuid);
    $grp  = first($gw['groups'] ?? [], fn($g) => ($g['id'] ?? '') === $grpUuid);
    $member = in_array($stuUuid, $grp['studentIds'] ?? [], true);
    $studentInCourse = in_array($stuUuid, array_column($course['students'] ?? [], 'id'), true);
    echo "  registro_asistencia_presente=" . ($rec ? 'SI(' . ($rec['attendance'] ?? '?') . ')' : 'NO') . "\n";
    echo "  miembro_en_grupo=" . ($member ? 'SI' : 'NO') . "\n";
    echo "  estudiante_en_curso=" . ($studentInCourse ? 'SI' : 'NO') . "\n";

    if (!$rec || !$member || !$studentInCourse) {
        throw new RuntimeException('El estado pre-borrado no es el esperado');
    }

    echo "7) DELETE estudiante (destroyStudent)\n";
    check(api('DELETE', "/courses/$courseId/students/$stuUuid", null, $token), 'DELETE students');

    echo "8) GET curso (post-borrado)\n";
    $r = api('GET', "/courses/$courseId", null, $token);
    $course = $r['body']['data'] ?? null;
    $unit = first($course['units'] ?? [], fn($u) => ($u['id'] ?? '') === $unitId);
    $ses  = first($unit['sessions'] ?? [], fn($s) => ($s['id'] ?? '') === $sesUuid);
    $rec  = first($ses['records'] ?? [], fn($rec) => ($rec['studentId'] ?? '') === $stuUuid);
    $gw   = first($unit['groupWorks'] ?? [], fn($g) => ($g['id'] ?? '') === $gwUuid);
    $grp  = first($gw['groups'] ?? [], fn($g) => ($g['id'] ?? '') === $grpUuid);
    $member = in_array($stuUuid, $grp['studentIds'] ?? [], true);
    $studentInCourse = in_array($stuUuid, array_column($course['students'] ?? [], 'id'), true);
    echo "  registro_asistencia_presente=" . ($rec ? 'SI' : 'NO') . "\n";
    echo "  miembro_en_grupo=" . ($member ? 'SI' : 'NO') . "\n";
    echo "  estudiante_en_curso=" . ($studentInCourse ? 'SI' : 'NO') . "\n";

    // Verificación en BD
    echo "9) verificación BD\n";
    $att = q($PDO, 'SELECT id, deleted_at FROM app_attendance WHERE estudiante_id = ?', [$estId]);
    $al  = q($PDO, 'SELECT id, deleted_at FROM app_alerts WHERE estudiante_id = ?', [$estId]);
    $sc  = q($PDO, 'SELECT id, deleted_at FROM app_activity_scores WHERE estudiante_id = ?', [$estId]);
    $mem = q($PDO, 'SELECT grupo_id, estudiante_id FROM app_activity_group_members m JOIN app_students e ON e.id = m.estudiante_id WHERE e.uuid = ?', [$stuUuid]);

    $attAllDeleted = count($att) > 0 && count(array_filter($att, fn($x) => $x['deleted_at'] !== null)) === count($att);
    $alAllDeleted  = count($al) > 0 && count(array_filter($al, fn($x) => $x['deleted_at'] !== null)) === count($al);
    $scAllDeleted  = count($sc) > 0 && count(array_filter($sc, fn($x) => $x['deleted_at'] !== null)) === count($sc);
    $membersGone   = count($mem) === 0;

    foreach ($att as $x) echo "  app_attendance id={$x['id']} deleted_at=" . ($x['deleted_at'] ?? 'NULL') . "\n";
    foreach ($al  as $x) echo "  app_alerts id={$x['id']} deleted_at=" . ($x['deleted_at'] ?? 'NULL') . "\n";
    foreach ($sc  as $x) echo "  app_activity_scores id={$x['id']} deleted_at=" . ($x['deleted_at'] ?? 'NULL') . "\n";
    foreach ($mem as $x) echo "  membresía residual grupo={$x['grupo_id']}\n";

    $pass = $attAllDeleted && $alAllDeleted && $scAllDeleted && $membersGone;
    echo "\n==== CASCADA: " . ($pass ? 'PASS ✅' : 'FAIL ❌') . " ====\n";
    echo "  asistencia soft-deleted: " . ($attAllDeleted ? 'SI' : 'NO') . "\n";
    echo "  alerta soft-deleted:     " . ($alAllDeleted ? 'SI' : 'NO') . "\n";
    echo "  score soft-deleted:      " . ($scAllDeleted ? 'SI' : 'NO') . "\n";
    echo "  membresías eliminadas:   " . ($membersGone ? 'SI' : 'NO') . "\n";
    exit($pass ? 0 : 2);
} finally {
    // Cleanup: borrado duro de TODA la rama de prueba
    try {
        $stmt = function ($sql, $p = []) use ($PDO) { $s = $PDO->prepare($sql); $s->execute($p); };
        $courseRow = q($PDO, 'SELECT id FROM app_courses WHERE uuid = ?', [$courseId]);
        if ($courseRow) {
            $cId = $courseRow[0]['id'];
            $unitRows = q($PDO, 'SELECT id FROM app_units WHERE curso_id = ?', [$cId]);
            foreach ($unitRows as $ur) {
                $uId = $ur['id'];
                $actRows = q($PDO, 'SELECT id FROM app_activities WHERE unidad_id = ?', [$uId]);
                foreach ($actRows as $ar) {
                    $aId = $ar['id'];
                    $grpIds = q($PDO, 'SELECT id FROM app_activity_groups WHERE activity_id = ?', [$aId]);
                    foreach ($grpIds as $g) {
                        $stmt('DELETE FROM app_activity_group_members WHERE grupo_id = ?', [$g['id']]);
                        $stmt('DELETE FROM app_activity_group_overrides WHERE grupo_id = ?', [$g['id']]);
                        $stmt('DELETE FROM app_activity_scores WHERE grupo_id = ?', [$g['id']]);
                        $stmt('DELETE FROM app_activity_groups WHERE id = ?', [$g['id']]);
                    }
                    $stmt('DELETE FROM app_activity_scores WHERE activity_id = ?', [$aId]);
                    $stmt('DELETE FROM app_activity_criteria WHERE activity_id = ?', [$aId]);
                    $stmt('DELETE FROM app_activities WHERE id = ?', [$aId]);
                }
                $sesIds = q($PDO, 'SELECT id FROM app_sessions WHERE unidad_id = ?', [$uId]);
                foreach ($sesIds as $s) { $stmt('DELETE FROM app_attendance WHERE sesion_id = ?', [$s['id']]); }
                $stmt('DELETE FROM app_sessions WHERE unidad_id = ?', [$uId]);
                $stmt('DELETE FROM app_units WHERE id = ?', [$uId]);
            }
            $stmt('DELETE FROM app_activity_group_members WHERE estudiante_id IN (SELECT id FROM app_students WHERE curso_id = ?)', [$cId]);
            $stmt('DELETE FROM app_alerts WHERE curso_id = ?', [$cId]);
            $stmt('DELETE FROM app_students WHERE curso_id = ?', [$cId]);
            $stmt('DELETE FROM app_courses WHERE id = ?', [$cId]);
            echo "cleanup OK\n";
        } else {
            echo "cleanup: curso no encontrado (nada que borrar)\n";
        }
    } catch (\Throwable $e) {
        echo "cleanup ERROR: " . $e->getMessage() . "\n";
    }
}