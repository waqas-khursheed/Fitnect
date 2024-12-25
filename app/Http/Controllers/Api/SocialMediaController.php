<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Http\Resources\PostResource;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostView;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
// use Pawlox\VideoThumbnail\VideoThumbnail;

use Pawlox\VideoThumbnail\Facade\VideoThumbnail;

class SocialMediaController extends Controller
{
    use ApiResponser;

    /*
    |--------------------------------------------------------------------------
    |       POST MODULE
    |--------------------------------------------------------------------------
    */

    /** List post */
    public function listPost(Request $request)
    {
        $this->validate($request, [
            'offset'       =>   'required|numeric',
            'user_id'      =>   'nullable|exists:users,id'
        ]);

        $posts = Post::with('user', 'comments', 'likes.user')->withCount('comments', 'likes', 'post_views')->latest()

            ->when($request->has('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        $totalPosts = $posts->count();
        $posts = $posts->skip($request->offset)->take(10)->get();

        if (count($posts) > 0) {
            $data = [
                'total_posts'     =>  $totalPosts,
                'posts'           =>  PostResource::collection($posts)
            ];
            return $this->successDataResponse('Posts found successfully.', $data, 200);
        } else {
            return $this->errorResponse('No posts found.', 400);
        }
    }

    /** Detail post */
    public function detailPost(Request $request)
    {
        $this->validate($request, [
            'post_id'          =>      'required|exists:posts,id'
        ]);

        try {
            $posts = Post::whereId($request->post_id)->with('user', 'comments')->withCount('comments', 'likes', 'post_views')->first();
            $data = new PostResource($posts);
            return $this->successDataResponse('Post found successfully.', $data, 200);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** post view */
    public function postView(Request $request)
    {
        $this->validate($request, [
            'post_id'                   =>  'required|exists:posts,id'
        ]);

        $post_data = $request->only('post_id', 'description') + ['user_id' => auth()->id()];
        PostView::create($post_data);

        DB::commit();
        return $this->successResponse('Post has been viewed successfully.');
    }

    /** Create post */
    public function createPost(Request $request)
    {
        $this->validate($request, [
            'title'             =>  'required',
            'description'       =>  'required',
            'media'             =>  'required'
        ]);

        try {
            DB::beginTransaction();

            $media_path = null;
            $media_type = null;
            $media_thumbnail = null;

            if ($request->hasFile('media')) {

                $media_type = explode('/', $request->file('media')->getClientMimeType())[0];
                $media = strtotime("now") . mt_rand(100000, 900000) . '.' . $request->media->getClientOriginalExtension();
                $request->media->move(public_path('/media/post_media'), $media);
                $media_path = '/media/post_media/' . $media;

                if ($media_type == "video") {
                    $media_thumb_image = mt_rand() . time() . ".png";
                    $thumbnail = getcwd() . "/media/thumb/" . $media_thumb_image;
                    $cmd = sprintf('ffmpeg -ss 00:00:02 -i ' . getcwd() . $media_path . ' -frames:v 1 ' . $thumbnail);
                    exec($cmd);
                    $media_thumbnail = "/media/thumb/" . $media_thumb_image;
                }

                // // If thumbnail not created
                // if (!file_exists(asset($media_thumbnail))) {
                //     $media_thumbnail = '/media/thumb/default-thumb.jpg';
                // }
            }

            $post_data = $request->only('title', 'description') +
                ['media' => $media_path, 'media_type' => $media_type, 'media_thumbnail' => $media_thumbnail, 'user_id' => auth()->id()];
            Post::create($post_data);

            DB::commit();
            return $this->successResponse('Post has been created successfully.');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Edit post */
    public function editPost(Request $request)
    {
        $this->validate($request, [
            'post_id'                   =>  'required|exists:posts,id',
            'title'                 =>  Rule::requiredIf($request->has('title'))
        ]);

        try {
            DB::beginTransaction();

            $post = Post::whereId($request->post_id)->first();

            $media_path = $post->media;
            $media_type = $post->media_type;
            $media_thumbnail = $post->media_thumbnail;


            if ($request->hasFile('media')) {

                if (file_exists(asset($media_path))) {
                    unlink(asset($media_path));
                }

                if (file_exists(asset($media_thumbnail))) {
                    unlink(asset($media_thumbnail));
                }

                $media_type = explode('/', $request->file('media')->getClientMimeType())[0];
                $media = strtotime("now") . mt_rand(100000, 900000) . '.' . $request->media->getClientOriginalExtension();
                $request->media->move(public_path('/media/post_media'), $media);
                $media_path = '/media/post_media/' . $media;

                if ($media_type == "video") {
                    $media_thumb_image = mt_rand() . time() . ".png";
                    $thumbnail = getcwd() . "/media/thumb/" . $media_thumb_image;
                    $cmd = sprintf('ffmpeg -ss 00:00:02 -i ' . getcwd() . $media_path . ' -frames:v 1 ' . $thumbnail);
                    exec($cmd);
                    $media_thumbnail = "/media/thumb/" . $media_thumb_image;
                }

                // // If thumbnail not created
                // if (!file_exists(asset($media_thumbnail))) {
                //     $media_thumbnail = '/media/thumb/default-thumb.jpg';
                // }
            }

            $post_data = $request->only('title', 'description') +
                ['media' => $media_path, 'media_type' => $media_type, 'media_thumbnail' => $media_thumbnail];
            $updated = Post::whereId($request->post_id)->update($post_data);


            DB::commit();
            return $this->successResponse('Post has been updated successfully.');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Delete post */
    public function deletePost(Request $request)
    {
        $this->validate($request, [
            'post_id'         =>    'required|exists:posts,id'
        ]);

        $post = Post::whereId($request->post_id)->where(['user_id' => auth()->id()])->first();
        try {
            DB::beginTransaction();
            $post->delete();

            DB::commit();
            return $this->successResponse('Post has been deleted successfully.');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Post create comment  */
    public function postCreateComment(Request $request)
    {
        $this->validate($request, [
            'post_id'     =>    'required|exists:posts,id',
            'parent_id'   =>    'nullable|exists:comments,id',
            'comment'     =>    'required',
        ]);

        try {
            $data = $request->only('post_id', 'comment', 'parent_id') + ['user_id' => auth()->id()];
            $created = Comment::create($data);

            $post = Post::whereId($request->post_id)->with('user')->first();
            if ($post->user_id != auth()->id()) {

                // Notification
                $notification = [
                    'device_token'  =>   $post->user->device_token,
                    'sender_id'     =>   auth()->id(),
                    'receiver_id'   =>   $post->user->id,
                    'description'   =>   $request->comment,
                    'title'         =>    auth()->user()->first_name . ' ' . auth()->user()->last_name . ' comment on your post.',
                    'record_id'     =>   $request->post_id,
                    'type'          =>   'post_comment',
                    'created_at'    =>   now(),
                    'updated_at'    =>   now()
                ];
                if ($post->user->device_token != null && $post->user->push_notification == '1') {
                    push_notification($notification);
                }
                in_app_notification($notification);
            }

            $comment = Comment::whereId($created->id)->with('user')->first();

            return $this->successDataResponse('Comment has been created successfully.', new CommentResource($comment));
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Post update comment  */
    public function postUpdateComment(Request $request)
    {
        $this->validate($request, [
            'comment_id'     =>    'required|exists:comments,id',
            'comment'        =>    'required',
        ]);

        try {
            $data = $request->only('comment');
            Comment::whereId($request->comment_id)->update($data);
            return $this->successResponse('Comment has been updated successfully.');
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Post delete comment  */
    public function postDeleteComment(Request $request)
    {
        $this->validate($request, [
            'comment_id'     =>    'required|exists:comments,id',
        ]);

        try {
            Comment::whereId($request->comment_id)->delete();
            return $this->successResponse('Comment has been deleted successfully.');
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Post create like unlike  */
    public function postCreateLikeUnlike(Request $request)
    {
        $this->validate($request, [
            'record_id'    =>    'required',
            'like_type'    =>    'required|in:post' // comment
        ]);

        try {
            $like_type = $request->like_type;

            if ($like_type == 'post') {
                $hasPost = Post::whereId($request->record_id)->with('user')->first();
                if (empty($hasPost)) {
                    return $this->errorResponse('Post not found.', 400);
                }
                $user = $hasPost->user;
                $title = $hasPost->title;
            } else if ($like_type == 'comment') {
                $hasComment = Comment::whereId($request->record_id)->with('user')->first();
                if (empty($hasComment)) {
                    return $this->errorResponse('Comment not found.', 400);
                }
                $user = $hasComment->user;
                $title = $hasComment->comment;
            }

            $isLiked = Like::where(['user_id' => auth()->id(), 'record_id' => $request->record_id, 'like_type' => $request->like_type])->first();

            if (!empty($isLiked)) {
                $isLiked->delete();
                $message = ucfirst($like_type) . ' has been unliked successfully.';
            } else {
                $data = $request->only('record_id', 'like_type') + ['user_id' => auth()->id()];
                Like::create($data);
                $message = ucfirst($like_type) . ' has been liked successfully.';

                if ($user->id != auth()->id()) {
                    // Notification
                    $notification = [
                        'device_token'  =>   $user->device_token,
                        'sender_id'     =>   auth()->id(),
                        'receiver_id'   =>   $user->id,
                        'description'   =>   auth()->user()->first_name . ' ' . auth()->user()->last_name . ' like your ' . $like_type,
                        'title'         =>   auth()->user()->first_name . ' ' . auth()->user()->last_name . ' like your ' . $like_type,
                        'record_id'     =>   $request->record_id,
                        'type'          =>   $like_type . '_like',
                        'created_at'    =>   now(),
                        'updated_at'    =>   now()
                    ];
                    if ($user->device_token != null && $user->push_notification == '1') {
                        push_notification($notification);
                    }
                    in_app_notification($notification);
                }
            }
            return $this->successResponse($message);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }
}
