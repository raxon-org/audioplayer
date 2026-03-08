{{translation.import()}}
{{$meta.author = __('meta.author')}}
{{$meta.title = __('meta.title')}}
{{$meta.keywords = __('meta.keywords')}}
{{$meta.description = __('meta.description')}}
{{$request = request()}}
{{if(!is.empty($request.section.id))}}
    {{$id = $request.section.id}}
{{else}}
    {{$id = 'uuid-' + uuid()}}
{{/if}}