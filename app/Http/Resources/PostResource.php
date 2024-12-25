<?php

namespace App\Http\Resources;

use App\Exceptions\CustomValidationException;
use App\Models\Availability;
use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                =>  $this->id,
            'title'             =>  $this->title,
            'description'       =>  $this->description,
            'media'             =>  $this->media,
            'media_thumbnail'   =>  $this->media_thumbnail,
            'media_type'        =>  $this->media_type,
            'created_at'        =>  $this->created_at,
            'created_by'            => [
                'id'                    =>  $this->user->id,
                'first_name'            =>  $this->user->first_name,
                'last_name'             =>  $this->user->last_name,
                'profile_image'         =>  $this->user->profile_image,
                'user_type'             =>  $this->user->user_type
            ],
            'post_views_count'      =>  $this->post_views_count,
            'likes_count'           =>  $this->likes_count,
            'comments_count'        =>  $this->comments_count,
            'is_like'               => Like::where(['user_id' => auth()->id(), 'record_id' => $this->id, 'like_type' => 'post'])->count(),
            'comments'              => CommentResource::collection($this->comments),
        ];
    }
}
