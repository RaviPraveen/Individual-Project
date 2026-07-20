@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1'])

<div class="dropdown">
    <div data-bs-toggle="dropdown" role="button" style="cursor: pointer;">
        {{ $trigger }}
    </div>

    <ul class="dropdown-menu {{ $align === 'left' ? '' : 'dropdown-menu-end' }} {{ $contentClasses }}">
        {{ $content }}
    </ul>
</div>
