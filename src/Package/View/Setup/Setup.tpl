{{$register = Package.Raxon.Audioplayer:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Audioplayer:Import:role.system()}}
{{$flags = flags()}}
{{$options = options()}}
{{Package.Raxon.Audioplayer:Main:install($flags, $options)}}
{{/if}}