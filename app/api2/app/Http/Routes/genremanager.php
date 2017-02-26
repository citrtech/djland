<?php

use App\Genre as Genre;
use App\Subgenre as Subgenre;
use Validator as Validator;

Route::group(['middleware' => 'auth'], function(){
    Route::group(['prefix'=>'genres'], function(){
        //Get list of genres
        Route::get('/', function(){
            $result = Genre::all();
            if(!$result->isEmpty()) return Response::json($result);
            else return Response::json();
        });
        //Get a list of subgenres for a genre given the genre id
        Route::get('/subgenres/{id}', function($id){
            $rules = array(
                'id' => 'required|integer|min:1'
            );
            $data = ['id' => $id];
            $validator = Validator::make($data, $rules);
            if($validator->fails()) return response($validator->errors()->all(),422);
            else {
                try{
                    $subgenres = Subgenre::where('parent_genre_id', '=', $id)->get();
                    if($subgenres!=null) return $subgenres;
                    else return Response::json();
                } catch(Exception $e){
                    return $e->getMessage();
                }
            }
        });
        /** Use staff middleware for POST, PUT and DELETE routes so that
          * only staff can update the genre listings even if they
          * figured out how to API
          */
        Route::group(['middleware' => 'staff'], function(){
            //Create a genre
            Route::post('/', function(){
                $rules = array(
                    'genre' => 'required|regex:/^[\pL\-\_\/\\\~\!\@\#\$\&\*\ ]+$/u'
                );
                $validator = Validator::make(Input::all(), $rules);
                if($validator->fails()) return response($validator->errors()->all(),422);
                else{
                    try{
                        $genre = Genre::create([
                            'genre' => Input::get('genre'),
                            'default_crtc_category' => 20,
                            'created_by' => $_SESSION['sv_id'],
                            'updated_by' => $_SESSION['sv_id']
                        ]);
                        return Response::json($genre);
                    } catch(Exception $e){
                        return $e->getMessage();
                    }
                }
            });
            //Update a genre given it's id
            Route::put('/', function(){
                $rules = array(
                    'id' => 'required|integer|min:1',
                    'genre' => 'required|regex:/^[\pL\-\_\/\\\~\!\@\#\$\&\*\ ]+$/u'
                );
                $validator = Validator::make(Input::all(), $rules);
                if(!$validator->fails()) return response($validator->errors()->all(),422);
                else {
                    try{
                        $genre = Genre::find(Input::get('id'));
                        $prev_genre = $genre->genre;
                        $genre->genre = Input::get('genre');
                        $genre->modified_by = $_SESSION['sv_id'];
                        $genre->save();
                        return Response::json("Update genre " . $prev_genre . " to " . Input::get('genre'));
                    } catch(Exception $e){
                        return $e->getMessage();
                    }
                }
            });
            //Delete a genre given it's id
            Route::delete('/', function(){
                $rules = array(
                    'id' => 'required|integer|min:1'
                );
                $validator = Validator::make(Input::all(), $rules);
                if(!$validator->fails()) return response($validator->errors()->all(),422);
                else {
                    try{
                        $genre = Genre::find(Input::get('id'));
                        Genre::destroy(Input::get('id'));
                        return Response::json('The Genre \"' . $genre->genre .'\" has been successfully deleted.');
                    } catch(Exception $e){
                        return $e->getMessage();
                    }
                }
            });
        });
    });

    Route::group(['prefix'=>'subgenres'], function(){
        //Get list of subgenres
        Route::get('/', function(){
            if(Input::get('id') != null) $result=Subgenre::find(Input::get('id')->get(0));
            else $result = Subgenre::all();
            if(!$result->isEmpty()) return Response::json($result);
            else return Response::json();
        });
        //Get a subgenre given it's ID
        Route::get('/{id}', function($id){
            $result = Subgenre::find($id);
            if($result!=null) return Response::json($result);
            else return Response::json();
        });
        //Get a subgenre's parentgenre given the subgenre's id
        // (returns the name of the genre and not the id)
        Route::get('/{id}/parentgenre', function($id=id){
            $result = Subgenre::find($id);
            $parent = Genre::find($result->parent_genre_id)->genre;
            if(!$result->isEmpty() && $parent!=null) return Response::json($parent);
            else return Response::json();
        });
        //Create a subgenre
        Route::post('/', function(){
            //
            $rules = array(
                'parent_genre_id' => 'required|integer|min:1',
                'subgenre' => 'required|regex:/^[\pL\-\_\/\\\~\!\@\#\$\&\*\ ]+$/u'
            );
            $validator = Validator::make(Input::all(), $rules);
            if($validator->fails()) return response($validator->errors()->all(),422);
            else{
                try{
                    $subgenre = Subgenre::create([
                        'subgenre' => Input::get('subgenre'),
                        'created_by' => $_SESSION['sv_id'],
                        'upddated_by' => $_SESSION['sv_id']
                    ]);
                    return Response::json($subgenre);
                } catch(Exception $e){
                    return $e->getMessage();
                }
            }
        });
        //Update a subgenre given an id
        Route::put('/', function(){
            $rules = array(
                'id' => 'required|integer',
                'subgenre' => 'required|regex:/^[\pL\-\_\/\\\~\!\@\#\$\&\*\ ]+$/u'
            );
            $validator = Validator::make(Input::all(), $rules);
            if(!$validator->fails()) return response($validator->errors()->all(),422);
            else {
                try{
                    $subgenre = Subgenre::find(Input::get('id'));
                    $prev_subgenre = $subgenre->subgenre;
                    $subgenre->subgenre = Input::get('subgenre');
                    $subgenre->updated_by = $_SESSION['sv_id'];
                    $subgenre->save();
                    return Response::json("Update subgenre " . $prev_subgenre . " to " . Input::get('subgenre'));
                } catch(Exception $e){
                    return $e->getMessage();
                }
            }
        });
        //Delete a subgenre given an id
        Route::delete('/', function(){
            $rules = array(
                'id' => 'required|integer'
            );
            $validator = Validator::make(Input::all(), $rules);
            if(!$validator->fails()) return response($validator->errors()->all(),422);
            else {
                try{
                    $subgenre = Subgenre::find(Input::get('id'));
                    Subgenre::destroy(Input::get('id'));
                    return Response::json('The Subgenre \"' . $subgenre->subgenre .'\" has been successfully deleted.');
                } catch(Exception $e){
                    return $e->getMessage();
                }
            }
        });
    });
});
