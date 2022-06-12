<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\ResponseFormatter;
use Laravel\Fortify\Rules\Password;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function register(Request $request){
        try{
            $request->validate([
                'name' => ['required','string','max:255'],
                'username' => ['required','string','max:255','unique:users'],
                'email' => ['email','required','string','max:255','unique:users'],
                'password' => ['required','string', new Password]
            ]);

            User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            $user = User::where('email', $request->email)->first();
            $tokenResult = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'User Registered');
        }catch(Exception $error){
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }

    public function login(Request $request){
        try {
            $request->validate([
                'email' => 'email|required',
                'password' => 'required'
            ]);

            $credentials = request(['email','password']);
            if(!Auth::attempt($credentials)){
                return ResponseFormatter::error([
                    'message' => 'Unauthorized',
                ],'Authentication Failed', 500);
            }

            $user = User::where('email', $request->email)->withCount('posts','cats', 'followers')->first();

            if(!Hash::check($request->password, $user->password,[])){
                throw new \Exception('Invalid Credentials');
            }

            $tokenResult = $user->createToken('authToken')->plainTextToken;
            return ResponseFormatter::success([
                'token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ],'Authenticated');

        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }

    public function all(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');

        if ($id) {
            $user = User::withCount(['posts', 'cats'])->with(['posts', 'cats'])->find($id);
            if ($user) {
                return ResponseFormatter::success(
                    $user,
                    'User data successfully retrieved'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'User data no available',
                    404
                );
            }
        }

        $user = User::withCount(['posts', 'cats','followers'])->with(['posts', 'cats']);
        if ($name) {
            $user->where('name', 'like', '%' . $name . '%');
        }

        return ResponseFormatter::success(
            $user->get(),
            'User data successfully retrieved'
        );
    }

    public function profile(Request $request){
        try {
            $validation = Validator::make($request->all(),[
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'unique:users,id,'.$request->user()->id],
                'bio' => ['required', 'string', 'max:100'],
            ]);

            if($validation->fails()){
                $error = $validation->errors()->all()[0];
                return ResponseFormatter::error([
                    'message' => 'Profile Failed to change',
                    'error' => $error
                ], 'Profile Failed to change',422);
            }else{
                $user = User::find($request->user()->id);
                $user->name = $request->name;
                $user->username = $request->username;
                $user->bio = $request->bio;
                $user->update();
                return ResponseFormatter::success($user, 'Profile Updated');
            }
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }

    public function changeEmail(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'email' => ['required', 'string', 'max:255', 'email']
            ]);

            if ($validation->fails()) {
                $error = $validation->errors()->all()[0];
                return ResponseFormatter::error([
                    'message' => 'Email Failed to change',
                    'error' => $error
                ], 'Email Failed to change', 422);
            } else {
                $user = User::find($request->user()->id);
                $user->email = $request->email;
                $user->update();
                return ResponseFormatter::success($user, 'Email Updated');
            }
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'password' => ['required', 'string', new Password]
            ]);

            if ($validation->fails()) {
                $error = $validation->errors()->all()[0];
                return ResponseFormatter::error([
                    'message' => 'Password Failed to change',
                    'error' => $error
                ], 'Password Failed to change', 422);
            } else {
                $user = User::find($request->user()->id);
                $user->password = Hash::make($request->password);
                $user->update();
                return ResponseFormatter::success($user, 'Password Updated');
            }
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }

    public function changePhoto(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'profile_photo_path' => ['nullable', 'image']
            ]);

            if ($validation->fails()) {
                $error = $validation->errors()->all()[0];
                return ResponseFormatter::error([
                    'message' => 'Profile Failed to change',
                    'error' => $error
                ], 'Profile Failed to change', 422);
            } else {
                $user = User::find($request->user()->id);
                if ($request->profile_photo_path && $request->profile_photo_path->isValid()) {
                    $googleConfigFile = file_get_contents(config_path('flowing-silo-350506-e0fbc96d1dcf.json'));
                    $storage = new StorageClient([
                        'keyFile' => json_decode($googleConfigFile, true)
                    ]);
                    $storageBucketName = config('googlecloud.storage_bucket');
                    $bucket = $storage->bucket($storageBucketName);
                    $photo = $request->file('profile_photo_path');
                    $image_path = $photo->getRealPath();
                    $photo_name = auth()->user()->name . '-' . time() . '.' . $photo->extension();
                    $fileSource = fopen($image_path, 'r');
                    $newFolderName = 'users';
                    $googleCloudStoragePath = $newFolderName . '/' . $photo_name;
                    $bucket->upload($fileSource, [
                        'name' => $googleCloudStoragePath
                    ]);
                    $user->profile_photo_path = $googleCloudStoragePath;
                }
                $user->update();
                return ResponseFormatter::success($user, 'Profile Updated');
            }
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');

        if ($id) {
            $user = User::destroy($id);
            if ($user) {
                return ResponseFormatter::success(
                    $user,
                    'User deleted successfully'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'User data no available',
                    404
                );
            }
        }
    }

    public function logout(Request $request){
        $token = $request->user()->currentAccessToken()->delete();
        return ResponseFormatter::success($token,'Token Revoked');
    }
}
