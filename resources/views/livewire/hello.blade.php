<?php

use App\Events\HelloSent;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, mount, on};

state([
'me'=>'',
'draft'=>'',
'received'=>[],

]);

mount(function(){
    $this->me=Str::random(4);
});

on([
    'echo:hello,HelloSent'=>function($event){
        $this->received[]=($event['from'] ?? '?'). ':'. ($event['message'] ?? '(heard it, no data)');
    }
]);

$send=function(){
    if(trim($this->draft) === ''){
        return;
    }

    broadcast(new HelloSent($this->draft,$this->me));
    $this->draft='';
};
?>


<div class="max-w-md mx-auto p-6">
    <h1 class="text-2xl font-bold mb-1">📡 Hello, Reverb</h1>
    <p class="text-sm text-gray-500 mb-4">This tab is <b>{{ $me }}</b>. Open this page in a second tab.</p>

    <form wire:submit="send" class="flex gap-2 mb-4">
        <input type="text" wire:model="draft" placeholder="Say something..." class="flex-1 border rounded px-3 py-2">
        <button class="px-4 py-2 bg-blue-600 text-white rounded">Send</button>
    </form>

    <ul class="space-y-1">
        @foreach ($received as $line)
            <li class="px-3 py-1 bg-gray-100 rounded">{{ $line }}</li>
        @endforeach
    </ul>
</div>