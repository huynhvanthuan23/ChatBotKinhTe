@extends('layouts.admin')

@section('title', 'Trạng thái hệ thống')

@section('content_header')
    <h1>Trạng thái hệ thống</h1>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Trạng thái Chatbot API</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.system.api-docs') }}" class="btn btn-sm btn-info mr-2">
                        <i class="fas fa-book"></i> Tài liệu API
                    </a>
                    <button type="button" class="btn btn-sm btn-tool" id="refresh-status">
                        <i class="fas fa-sync-alt"></i> Làm mới
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="status-indicator mb-4">
                    <div class="d-flex align-items-center">
                        <div class="status-badge mr-3 {{ $chatbotStatus['online'] ? 'bg-success' : 'bg-danger' }}" style="width: 20px; height: 20px; border-radius: 50%;"></div>
                        <h4 class="m-0">
                            @if($chatbotStatus['online'])
                                <span class="text-success">Online</span>
                            @else
                                <span class="text-danger">Offline</span>
                            @endif
                        </h4>
                    </div>
                    <p class="text-muted mt-2">{{ $chatbotStatus['message'] }}</p>
                </div>
                
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 40%">API Version</th>
                        <td>{{ $chatbotStatus['api_version'] }}</td>
                    </tr>
                    <tr>
                        <th>Kiểm tra lần cuối</th>
                        <td>{{ $chatbotStatus['last_checked'] }}</td>
                    </tr>
                    @if($chatbotStatus['online'] && !empty($chatbotStatus['resources']))
                        @foreach($chatbotStatus['resources'] as $key => $value)
                        <tr>
                            <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                            <td>
                                @if(is_bool($value))
                                    {!! $value ? '<span class="badge badge-success">Có</span>' : '<span class="badge badge-danger">Không</span>' !!}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh status
    document.getElementById('refresh-status').addEventListener('click', function() {
        window.location.reload();
    });
});
</script>
@stop 