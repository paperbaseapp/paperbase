<?php


namespace App\Http\Controllers;


use App\Models\Library;
use App\Models\User;

class LibraryController extends Controller
{
    public function create()
    {
        $data = $this->validateWith([
            'name' => 'string|unique:libraries,name|required',
        ]);

        $library = new Library();
        $library->name = $data['name'];
        $library->owner()->associate(User::current());
        $library->save();

        return response()->json($library);
    }

    public function getAll()
    {
        return User::current()->libraries->sortBy('name', SORT_NATURAL|SORT_FLAG_CASE)->values();
    }
}

