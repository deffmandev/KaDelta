<style>
body
{
        background: #2f363c;
        color: white;
        padding-top: 190px; /* Ajustez selon la hauteur de votre FrameTitre */

}

.Vignette
{
    display:none;
    border: 1px solid #716f6f;
    display: block;
    height: 73px;
    width: 172px;
    padding: 14px;
    position: relative;
    box-shadow: inset 4px 4px 10px 2px #00000087;
    margin: 14px;
}

.ViOff
{
    background: linear-gradient(#6f6c6c,#aaa,#6f6c6c);
}

.ViOnClim
{
    background: linear-gradient(#565eb7, #4451eb87, #565eb7)
}

.ViOnDefaut
{
    background: linear-gradient(#ed0000, #ff6d3496, #b90e0e)
}

.ViOnChaud
{
    background: linear-gradient(#cccd21, #e9ac38c4, #bfb42d)
}

.ViT1
{
    top: 8px;
    left: 6px;
    position: absolute;
    font-size: 23px;
    font-weight: 700;
    font-family: math;
    width: 96%;
    height: 26px;
    overflow: hidden;
}

.ViT2
{
    top: 37px;
    left: 19px;
    position: absolute;
    font-size: 25px;
    font-weight: 900;
    font-family: math;
}

.ViT3
{
    top: 73px;
    right: 7px;
    position: absolute;
    font-size: 19px;
    font-weight: 100;
    font-family: math;
    width: 83px;
    text-align: end;
}

.ViT4
{
    top: 70px;
    left: 14px;
    position: absolute;
    font-size: 20px;
    font-weight: 100;
    font-family: math;
}

.ViT5
{
    display: none;
}

.CadreUnites
{
    display: flex;
    flex-wrap: wrap;
    padding: 9px;
    margin: auto;
    flex-direction: row;
    justify-content: flex-start;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.OverScreen
{
    display : none;
    position: fixed;
    top: 0;
    left: 0;
    background: rgb(55 82 116 / 44%);
    backdrop-filter: blur(5px);
    width: 100%;
    height: 100%;
    z-index: 1000;
}


.FrameTitre
{
    display: flex;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    box-shadow: 1px 1px red;
    height: 196px;
    z-index: 100;
    background: #2f363c;
    box-shadow: 4px 6px 5px #0000007d;
}

.TitreGroupe
{
    position: absolute;
    top: 95px;
    left: 173px;
    display: flex;
    flex-direction: row;
    align-content: center;
    justify-content: center;
}


.groupe-btn 
{
    background: linear-gradient(79deg, #6a11cb 0%, #2575fc 100%);
    color: #fff;
    padding: 16px 36px;
    border: none;
    border-radius: 20px;
    font-size: 1.0rem;
    font-weight: bold;
    box-shadow: 2px 3px 20px rgba(38, 50, 56, 0.2);
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
    outline: none;
    letter-spacing: 1px;
    margin-right: 2em;
}
.groupe-btn:hover, .groupe-btn:hover 
        {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 8px 32px rgba(38, 50, 56, 0.25);
            background: linear-gradient(90deg, #2575fc 0%, #6a11cb 100%);
        }

.horloges
{
    position: absolute;
    top: 19px;
    right: 32px;
    font-size: 1.4em;
    padding: 6px;
    font-family: math;
    letter-spacing: 1px;
    color: #fff;
    font-weight: 400;
    text-align: center;
    z-index: 100;
}

</style>