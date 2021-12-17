<?php

namespace App\Http\Controllers\Apis;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Exception;


class UtilityController extends Controller
{
    public function getStates() {
        try{
            $data = \App\Models\State::all();
            return response()->json([
                "message" => "Data retrieved succesfully",
                'data' => $data,
                'status' => 'success',
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }

    public function Countries() {
        try{
            $data = \App\Models\Country::all();
            return response()->json([
                "message" => "Data retrieved succesfully",
                'data' => $data,
                'status' => 'success',
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }



    public function EduQUalification() {
        try{
            $data = \App\Models\EducationalQualification::all();
            return response()->json([
                "message" => "Data retrieved succesfully",
                'data' => $data,
                'status' => 'success',
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }


    public function EmploymentStatus() {

        try{
            $data = \App\Models\EmploymentStatus::all();
            return response()->json([
                "message" => "Data retrieved succesfully",
                'data' => $data,
                'status' => 'success',
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }

    public function FAQs() {
        try{
            $data = \App\Models\FAQ::orderBy('id','desc')->get();
            return response()->json([
                "message" => "Data retrieved succesfully",
                'data' => $data,
                'status' => 'success',
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }


    public function IDcard() {

        try{
            $data = \App\Models\IdCardType::all();
            return response()->json([
                "message" => "Data retrieved succesfully",
                'data' => $data,
                'status' => 'success',
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }

    public function Cities() {

        try {
            $data = \App\Models\LGA::all();
            return response()->json([
                "message" => "Data retrieved succesfully",
                'data' => $data,
                'status' => 'success',
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }



    public function SecretQuestions()
    {
        try {
            $data = \App\Models\SecretQuestions::all();
            return response()->json(['data' => $data],200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }

    public function GetPlans()
    {
        try {
            $data = \App\Models\Plan::all();
            return response()->json(['data' => $data],200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()],500);
        }
    }




}
