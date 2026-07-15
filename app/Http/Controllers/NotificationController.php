<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function send(Request $request, FirebaseService $firebase)
    {
        $request->validate([
            'token' => 'required|string',
            'title' => 'required|string',
            'body'  => 'required|string',
        ]);

        $firebase->sendNotification(
            $request->token,
            $request->title,
            $request->body,
            [
                'title' => $request->title,
                'body' => $request->body,
                'type' => 'task',
                'task_id' => '123',
                'action_1' => 'ACEPTAR',
                'action_2' => 'RECHAZAR',
                // 'actions' => json_encode([
                //     ['id' => 'done', 'label' => 'Hecho'],
                //     ['id' => 'later', 'label' => 'Luego'],
                // ]
                //)
            ]
        );

        return response()->json(['status' => 'ok']);
    }
}