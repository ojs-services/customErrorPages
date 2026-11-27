# Özel Hata Sayfaları (Custom Error Pages)

**OJS 3.3** eklentisi. OJS'nin düz `404 Not Found` iletisinin yerine, derginin kendi temasında, üst bilgisi, alt bilgisi ve menüsüyle birlikte anlaşılır bir hata sayfası gösterir. Her temayla çalışır, OJS'de değişiklik gerektirmez.

Temalı sayfa şu durumlarda gösterilir: bilinmeyen sayfa, silinmiş makale, bulunmayan sayı, bulunmayan dergi yolu ve bulunmayan dosya. Bulunmayan dosyada sayfa makaleye ya da sayıya geri bağlantı verir.

## Stil seçimi

Her dergi, eklenti ayarlarındaki 12 stilden birini seçer. Fotoğraflı stiller küçük bir görsel yükler (yaklaşık 100 KB); renk stilleri ek bir şey yüklemez. *Kendi görseliniz* (12. stil) ile istediğiniz görseli kullanabilirsiniz; örneğin OJS'ye yüklediğiniz ve herkese açık yaptığınız bir görsel.

## Kurulum

1. [Releases](../../releases) sayfasından `customErrorPages-ojs3.3-<sürüm>.tar.gz` dosyasını (3.0.0.x sürümü) indirin. OJS 3.4 ve 3.5 için `customErrorPages-ojs3.4-3.5-<sürüm>.tar.gz` (4.x sürümü) kullanılır.
2. OJS'de *Ayarlar → Web Sitesi → Eklentiler → Yeni eklenti yükle* yolunu izleyip dosyayı seçin.
3. **Custom Error Pages** eklentisini istediğiniz her dergide etkinleştirin.
4. İsteğe bağlı: *Custom Error Pages → Ayarlar*'dan bir stil seçin.

**1.4.0.0 ya da daha eski sürümden yükseltme:** önce eski eklentiyi eklenti listesinden silin, sonra yeni paketi yükleyin. Ayarlarınız korunur (farklı eklenti adı kullanan 1.1.x hariç).

## Gereksinimler

- OJS 3.3.0-x
- PHP 7.3 ya da üstü

## Lisans

GNU General Public License v3. Bkz. [LICENSE](LICENSE).

© OJS Services — info@ojs-services.com
