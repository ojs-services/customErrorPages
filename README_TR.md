# Özel Hata Sayfaları (Custom Error Pages)

**OJS 3.4 ve 3.5** eklentisi. OJS'nin düz `404 Not Found` iletisinin yerine, derginin kendi temasında, üst bilgisi, alt bilgisi ve menüsüyle birlikte anlaşılır bir hata sayfası gösterir. Her temayla çalışır, OJS'de değişiklik gerektirmez.

Temalı sayfa şu durumlarda gösterilir: bilinmeyen sayfa, silinmiş makale, bulunmayan sayı ve bulunmayan dosya. Bulunmayan dosyada sayfa makaleye ya da sayıya geri bağlantı verir.

Tek istisna, dergi yolu olmayan bir adrestir (örneğin `/index.php/boyle-bir-dergi-yok`): OJS 3.4 ve 3.5 bu adresi hiçbir eklenti yüklenmeden yanıtlar, bu yüzden orada OJS'nin düz 404'ü kalır.

## Stil seçimi

Her dergi, eklenti ayarlarındaki 12 stilden birini seçer. Fotoğraflı stiller küçük bir görsel yükler (yaklaşık 100 KB); renk stilleri ek bir şey yüklemez. *Kendi görseliniz* (12. stil) ile istediğiniz görseli kullanabilirsiniz; örneğin OJS'ye yüklediğiniz ve herkese açık yaptığınız bir görsel.

## Kurulum

1. [Releases](../../releases) sayfasından `customErrorPages-ojs3.4-3.5-<sürüm>.tar.gz` dosyasını (4.x sürümü) indirin. Aynı paket OJS 3.4 ve 3.5 içindir.
2. OJS'de *Ayarlar → Web Sitesi → Eklentiler → Yeni eklenti yükle* yolunu izleyip dosyayı seçin.
3. **Custom Error Pages** eklentisini istediğiniz her dergide etkinleştirin.
4. İsteğe bağlı: *Custom Error Pages → Ayarlar*'dan bir stil seçin.

**OJS 3.3:** `customErrorPages-ojs3.3-<sürüm>.tar.gz` paketini (3.0.0.x sürümü) kullanın. Eklentinin adı ve ayarları her OJS hattında aynıdır.

## Gereksinimler

- OJS 3.4.x ya da 3.5.x
- OJS'nizin istediği PHP sürümü (OJS 3.4 için 8.0 ya da üstü, OJS 3.5 için 8.2 ya da üstü)

## Lisans

GNU General Public License v3. Bkz. [LICENSE](LICENSE).

© OJS Services — info@ojs-services.com
