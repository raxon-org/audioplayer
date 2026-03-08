{{RAX}}
{{$request = request()}}
{{$request.method = 'replace-with-or-append-to'}}
{{$request.target = 'section[name="application-' + config('controller.name') + '-navigation"]'}}
{{$request.append.to = 'section[name="application-' + config('controller.name') +'"]'}}
{{$menu = menu.get()}}
{{block.html()}}
<section name="application-{{config('controller.name')}}-navigation">
    <div class="start">
        <img class="start" src="/Application/{{config('controller.title')}}/Image/Icon/Puzzle.png" alt="Start" />
    </div>
    <nav class="menu display-none">
        <ul>
            {{if(!is.empty($menu) && is.array($menu))}}
                {{foreach($menu as $title => $list)}}
                    <li class="title">{{$title}}</li>
                    {{foreach($list as $nr => $node)}}
                        <a
                            data-url="{{$node.url|>default:''}}"
                            data-method="{{$node.method|>default:''}}"
                            data-target="{{$node.target|>default:''}}"
                            data-header-with-authorization="true"
                        >
                            <li class="item">
                                <p>{{if(!is.empty($node.svg))}}<img src="{{$node.svg}}" alt="icon" class="icon" />{{/if}}{{$node.name|>string.replace:' ':'&nbsp;'}}</p>
                            </li>
                        </a>
                    {{/foreach}}
                {{/foreach}}
            {{else}}
                <li class="title">Failed to load navigation menu...</li>
            {{/if}}
        </ul>
    </nav>
    <div class="task-bar">
    </div>
    <div class="clock">
        <div class="time"></div>
        <div class="week"></div>
        <div class="date"></div>
    </div>
</section>
{{/block}}