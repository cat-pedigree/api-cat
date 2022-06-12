<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Cats;
use App\Models\Album;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Validator;


class CatController extends Controller
{
    public function all(Request $request){
        $id = $request->input('id');
        $user_id = $request->input('user_id');
        $name = $request->input('name');
        $breed = $request->input('breed');
        $gender = $request->input('gender');
        $color = $request->input('color');
        $age = $request->input('age');

        if($id){
            $cat = Cats::with('user')->find($id);
            if($cat){
                return ResponseFormatter::success(
                    $cat,
                    'cat data successfully retrieved'
                );
            }else{
                return ResponseFormatter::error(
                    null,
                    'Cat data no available',
                    404
                );
            }
        }

        $cat = Cats::with('user');

        if($user_id){
            $cat->where('user_id', $user_id);
        }

        if($name){
            $cat->where('name','like','%'.$name.'%');
        }
        if($breed){
            $cat->where('breed','like','%'.$breed.'%');
        }

        if($gender){
            $cat->where('gender','like','%'.$gender.'%');
        }

        if($color){
            $cat->where('color','like','%'.$color.'%');
        }

        if($age){
            $cat->where('age','like','%'.$age.'%');
        }

        return ResponseFormatter::success(
            $cat->get(),
            'cat data successfully retrieved'
        );
    }

    public function store(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
               'name' => ['required', 'string', 'max:255'],
                'breed' => ['required', 'string', 'max:255'],
                'gender' => ['required', 'max:255'],
                'color' => ['required', 'string', 'max:255'],
                'eye_color' => ['required', 'string', 'max:255'],
                'hair_color' => ['required', 'string', 'max:255'],
                'ear_shape' => ['required', 'string', 'max:255'],
                'weight' => ['required','regex:/^[0-9]+(\.[0-9][0-9]?)?$/'],
                'age' => ['required', 'integer'],
                'photo' => ['required','image','mimes:jpeg,png,jpg,svg'],
                'isWhite' => ['required', 'integer'],
                'story' => ['required'],
            ]);

            if ($validation->fails()) {
                $error = $validation->errors()->all()[0];
                return ResponseFormatter::error([
                    'message' => 'Failed to add data',
                    'error' => $error
                ], 'Failed to add data', 422);
            } else {
                if ($request->photo && $request->photo->isValid()) {
                    $googleConfigFile = file_get_contents(config_path('flowing-silo-350506-e0fbc96d1dcf.json'));
                    $storage = new StorageClient([
                        'keyFile' => json_decode($googleConfigFile, true)
                    ]);
                    $storageBucketName = config('googlecloud.storage_bucket');
                    $bucket = $storage->bucket($storageBucketName);
                    $photo = $request->file('photo');
                    $image_path = $photo->getRealPath();
                    $photo_name = auth()->user()->name . '-' . time() . '.' . $photo->extension();
                    $fileSource = fopen($image_path, 'r');
                    $newFolderName = 'cats';
                    $googleCloudStoragePath = $newFolderName . '/' . $photo_name;
                    $bucket->upload($fileSource, [
                        'name' => $googleCloudStoragePath
                    ]);
                }
                $cat = Cats::create([
                    'user_id' => $request->user()->id,
                    'name' => $request->name,
                    'breed' => $request->breed,
                    'gender' => $request->gender,
                    'color' => $request->color,
                    'eye_color' => $request->eye_color,
                    'hair_color' => $request->hair_color,
                    'ear_shape' => $request->ear_shape,
                    'weight' => $request->weight,
                    'age' => $request->age,
                    'photo' => $googleCloudStoragePath,
                    'lat' => $request->lat,
                    'lon' => $request->lon,
                    'isWhite' => $request->isWhite,
                    'story' => $request->story
                ]);
                return ResponseFormatter::success($cat, 'Data added successfully');
            }
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }

    public function search(Request $request){
        $breed = $request->input('breed');
        $color = $request->input('color');
        $gender = $request->input('gender');
        $is_white = $request->input('is_white');

        $cat = Cats::with('user');

        if($breed){
            $cat = Cats::where('breed', $breed);
        }

        if($color){
            $cat = Cats::where('color',$color);
        }

        if ($is_white) {
            $cat = Cats::where('isWhite', $is_white);
        }

        if ($cat) {
            return ResponseFormatter::success(
                $cat->where('gender', '!=', $gender)->get(),
                'cat data successfully retrieved'
            );
        } else {
            return ResponseFormatter::error(
                null,
                'Cat data no available',
                404
            );
        }
    }

    public function destroy(Request $request){
        $id = $request->input('id');

        if ($id) {
            $cat = Cats::destroy($id);
            if ($cat) {
                return ResponseFormatter::success(
                    $cat,
                    'data deleted successfully'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Cat data no available',
                    404
                );
            }
        }
    }

    public function update(Request $request){
        try {
            $id = $request->input('id');
            if ($id) {
                $cat = Cats::find($id);
                $validation = Validator::make($request->all(), [
                    'name' => ['required', 'string', 'max:255'],
                    'breed' => ['required', 'string', 'max:255'],
                    'gender' => ['required', 'max:255'],
                    'color' => ['required', 'string', 'max:255'],
                    'eye_color' => ['required', 'string', 'max:255'],
                    'hair_color' => ['required', 'string', 'max:255'],
                    'ear_shape' => ['required', 'string', 'max:255'],
                    'weight' => ['required', 'regex:/^[0-9]+(\.[0-9][0-9]?)?$/'],
                    'age' => ['required', 'integer'],
                    'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,svg|max:2048'],
                    'isWhite' => ['required', 'integer'],
                    'story' => ['required'],
                ]);
                if ($validation->fails()) {
                    $error = $validation->errors()->all()[0];
                    return ResponseFormatter::error([
                        'message' => 'Cat Failed to change',
                        'error' => $error
                    ], 'Cat Failed to change', 422);
                } else {
                    if ($cat) {
                        $cat->name = $request->name;
                        $cat->breed = $request->breed;
                        $cat->gender = $request->gender;
                        $cat->color = $request->color;
                        $cat->eye_color = $request->eye_color;
                        $cat->hair_color = $request->hair_color;
                        $cat->ear_shape = $request->ear_shape;
                        $cat->weight = $request->weight;
                        $cat->age = $request->age;
                        $cat->photo = $request->photo;
                        if ($request->photo && $request->photo->isValid()) {
                            $slug = Str::slug($request->name);
                            $fileName = 'photo-' . $slug . '-' . time() . '.' . $request->photo->extension();
                            $request->photo->storeAs('public/cats', $fileName);
                            $path = "cats/$fileName";
                            $cat->photo = $path;
                        }
                        $cat->lat = $request->lat;
                        $cat->lon = $request->lon;
                        $cat->update();
                        return ResponseFormatter::success(
                            $cat,
                            'cat updated'
                        );
                    } else {
                        return ResponseFormatter::error(
                            null,
                            'Cat data no available',
                            404
                        );
                    }
                }
            }else{
                return ResponseFormatter::error(
                    null,
                    'Cat data no available',
                    404
                );
            }
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }
}
