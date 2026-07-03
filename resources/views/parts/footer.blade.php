<footer class="footer">
    <div class="container">
        <div class="footer__top">
            <div class="row">
                <div class="col-md-3">
                    <h6 class="footer__title">Office</h6>
                    <p>742 Evergreen Terrace,<br>Springfield, OR 97403, USA</p>
                </div>
                <div class="col-md-2">
                    <h6 class="footer__title">Contact</h6>
                    <p>
                        <a href="tel:18052943517">1.805.294.3517</a>
                        <br>
                        <a href="mailto:contact@info.org">contact@info.org</a>
                    </p>
                </div>
                <div class="col-md-4">
                    <h6 class="footer__title">Follow</h6>
                    <div class="footer__social">
                        <a href="" target="_blank"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="" target="_blank"><i class="fa-brands fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h6 class="footer__title">Get our newsletter</h6>
                    <div class="footer__newsletter">
                        <form action="">
                            <input type="text" name="" class="txt" placeholder="Enter ">
                            <button type="submit" name="" class="sbm">
                                <img src="{{ asset('img/svg/submit.svg') }}" alt="Submit">
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer__bottom">
            <div class="row flex-row-reverse">
                <div class="col-md-6">
                    <div class="footer__links">
                        @if ($footerMenu)
                            @foreach ($footerMenu->items as $item)
                                @continue(! $item->is_visible)
                                <a href="{{ $item->href() }}" target="{{ $item->target }}" @if ($item->target === '_blank') rel="noopener noreferrer" @endif>{{ $item->label }}</a>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="footer__copyright">2026 © Copy</span>
                </div>
            </div>
        </div>
    </div>
</footer>

<div class="overlay"></div>

<div class="loader">
    <div class="loader-inner line-spin-fade-loader">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>
</div>

<div class="modal modal-example fade in show" id="modalExample" tabindex="-1" role="dialog" aria-labelledby="modalExample" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" tabindex="0">Modal Example</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">

            </div>
        </div>
    </div>
</div>
