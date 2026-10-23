@extends('errors.layout')

@section('code', '403')
@section('heading', 'Bạn không có quyền truy cập')
@section('message', $exception->getMessage() ?: 'Trang này chỉ dành cho tài khoản có quyền phù hợp.')
