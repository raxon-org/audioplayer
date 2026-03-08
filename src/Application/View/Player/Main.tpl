{{require(config('controller.dir.view') + config('controller.title') + '/Init.tpl')}}
{{$request.method = 'replace-with-or-append-to'}}
{{$request.target = html.target.create('section', ['name' => config('controller.name') + '-main'])}}
{{$request.append.to = 'body'}}
{{require(config('controller.dir.view') + config('controller.title') + '/Section.tpl')}}
{{script('module')}}
{{require(config('controller.dir.view') + config('controller.title') + '/Module/' + config('controller.title') + '.js')}}
{{/script}}