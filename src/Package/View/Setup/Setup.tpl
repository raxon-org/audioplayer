{{$register = Package.Raxon.AudioPlayer:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.AudioPlayer:Import:role.system()}}
{{$flags = flags()}}
{{$options = options()}}
{{Package.Raxon.AudioPlayer:Main:install($flags, $options)}}
{{/if}}