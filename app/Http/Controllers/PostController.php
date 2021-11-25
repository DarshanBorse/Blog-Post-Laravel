<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post as RequestsPost;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
        $post =  Post::LatestWithRelation()->get();
        // dd($post);
        return view('posts', compact('post'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('post.add');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(RequestsPost $request)
    {
        $validation = $request->validated();
        $validation['user_id'] = $request->user()->id;
        Post::create($validation);
        $request->session()->flash('status', 'Post is Created!');
        return redirect()->route('post.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // $post = Post::with(['comment' => function($query){
        //     $query->latest();
        // }],'user')->FindOrFail($post);

        // Cache::forget("blog-posts-{$id}");

        $posts = Cache::remember("blog-posts-{$id}", now()->addMinutes(10), function() use($id) {
            return Post::with('comment')
            ->with('user')
            ->with('tags')
            ->with('comment.user')
            ->Find($id);
        });


        $sessionId = session()->getId();
        $counterKeys = "post-{$id}-counter";
        $usersKey = "post-{$id}-user";

        $users = Cache::get($usersKey, []);
        $usersUpdate = [];
        $difference = 0;
        $now = now();
        foreach ($users as  $session => $lastVisit) {
            if ($now->diffInMinutes($lastVisit) >= 1) {
                $difference --;
            }else{
                $usersUpdate[$session] = $lastVisit;
            }
        }

        if (!array_key_exists($sessionId, $users) || $now->diffInMinutes($users[$sessionId]) >= 1) {
            $difference ++;
        }

        $usersUpdate[$sessionId] = $now;

        Cache::forever($usersKey, $usersUpdate);

        if (!Cache::has($counterKeys)) {
            Cache::forever($counterKeys, 1);
        }else{
            Cache::increment($counterKeys, $difference);
        }
        
        $counter = Cache::get($counterKeys);

        $post = $posts;
        return view('content', compact('post','counter'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function edit($posts)
    {
        $post = Post::FindOrFail($posts);
        $this->authorize('update', $post);
        // Gate::authorize('posts.update', $post);
        // if (Gate::denies('update_post', $post)) {
        //     abort(403, "You can't edit this post");
        // };
        return view('post.edit', compact('post'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function update(RequestsPost $request,  $post)
    {
        $post = Post::FindOrFail($post);
        $this->authorize('update', $post);
        $validation = $request->validated();
        $post->fill($validation);
        $post->save();

        $request->session()->flash('status', 'Post is updated!');
        return redirect()->route('post.show',$post);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy( $post)
    {
        $posts = Post::FindOrFail($post);
        $this->authorize('delete', $posts);
        // Gate::authorize('posts.delete',$posts);
        // if (Gate::denies('delete_post', $posts) ) {
        //     abort(403, "You can't delete this post");
        // };
        $posts->delete();
        session()->flash('status', 'Post is Deleted!');
        return redirect()->route('post.index');
    }
}
