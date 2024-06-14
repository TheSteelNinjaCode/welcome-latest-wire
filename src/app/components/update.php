<?php

use Lib\Prisma\Classes\Prisma;
use Lib\Validator;
use Lib\StateManager;

$prisma = new Prisma();
$state = new StateManager();

$id = $state->getState('id') ?? '';
$title = $state->getState('title') ?? '';

function update($data)
{
    global $prisma, $state;
    if (!Validator::string($data->title)) return;
    $prisma->todo->update([
        'where' => ['id' => $data->id],
        'data' => ['title' => $data->title]
    ]);

    $state->setState('isUpdate', false);
}

?>

<form class="flex items-center mb-4" onsubmit="update">
    <input type="hidden" name="id" value="<?= $id ?>" />
    <input type="text" placeholder="Edit todo..." class="flex-1 px-4 py-2 rounded-l-md bg-gray-100 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" name="title" required value="<?= $title ?>" />
    <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-r-md">Edit</button>
</form>