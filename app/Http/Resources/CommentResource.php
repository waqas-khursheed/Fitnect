<?php

namespace App\Http\Resources;

use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    =>  $this->id,
            'comment'               =>  $this->comment,
            // 'likes_count'           =>  $this->likes_count($this->id),
            // 'is_like'               =>  Like::where(['user_id' => auth()->id(), 'record_id' => $this->id, 'like_type' => 'comment'])->count(),
            'created_at'            =>  $this->created_at,
            'user'            => [
                'id'                    =>  $this->user->id,
                'first_name'            =>  $this->user->first_name,
                'last_name'             =>  $this->user->last_name,
                'profile_image'         =>  $this->user->profile_image,
                'user_type'             =>  $this->user->user_type
            ],
            // 'child_comments'         => CommentResource::collection($this->child_comments)
        ];
    }

    // private function likes_count($id)
    // {
    //     return Like::where(['record_id' => $id, 'like_type' => 'comment'])->count();
    // }
}
